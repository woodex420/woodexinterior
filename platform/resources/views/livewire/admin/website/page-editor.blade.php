@use('App\Domain\Website\Enums\BlockType')
@use('App\Domain\Website\Enums\PageStatus')

@php
    $isPublished = $status === \App\Domain\Website\Enums\PageStatus::Published->value;
    $canPublish = auth()->user()->can('website.publish');
    $saved = $page !== null;
@endphp

<div x-data="{ previewTimer: null }"
     x-on:scroll-to-block.window="
        const card = document.getElementById('blk-' + $event.detail.key);
        if (card) {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    "
     x-on:preview-stale.window="clearTimeout(previewTimer); previewTimer = setTimeout(() => $wire.refreshPreview(), 900)"
     x-on:page-created.window="history.replaceState({}, '', $event.detail.url)">
    <x-admin.page-header :title="$saved ? $title : 'New page'"
                         :description="$saved ? 'Edit the page, then publish it. Everything on this page is editable: the text, the sections, the photos and what search engines read.' : 'A page of the public website. Give it a title and a URL, add sections, then publish.'"
                         :breadcrumbs="['Website' => null, 'Pages' => route('admin.website.pages'), ($saved ? $title : 'New page') => null]">
        <x-slot:actions>
            @if ($saved)
                <x-admin.button variant="ghost" :href="$page->canonical()" target="_blank" rel="noopener">View{{ $isPublished ? '' : ' draft' }}</x-admin.button>
            @endif
            <x-admin.button variant="secondary" icon="save" wire:click="save(false)">Save draft</x-admin.button>
            @if ($canPublish)
                <x-admin.button icon="globe" wire:click="save(true)">{{ $isPublished ? 'Republish' : 'Publish' }}</x-admin.button>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @if ($issues !== [])
        <div class="mb-6 rounded-2xl border border-warning-300 bg-warning-50 p-4 text-theme-sm text-warning-800" role="alert">
            <p class="font-medium">This page is not ready to publish yet</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($issues as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-admin.card title="Preview" description="The page as a visitor sees it. It follows your edits — the draft saves itself and the frame reloads, the way the old admin's builder did. Click any section in it to jump to that section's form.">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile'] as $device => $label)
                    <button type="button" wire:click="setPreviewDevice('{{ $device }}')"
                            @class(['wx-seg-btn' => true, 'border-brand-500 bg-brand-50 text-brand-700 dark:border-brand-500 dark:bg-white/5 dark:text-brand-300' => $previewDevice === $device])>{{ $label }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-1.5 text-theme-xs text-gray-500 dark:text-gray-400">
                    <input type="checkbox" wire:model.live="previewLive" class="rounded border-gray-300"> Preview as I type
                </label>
                @if ($saved)
                    <a href="{{ $page->path() }}?preview=1" target="_blank" rel="noopener" class="wx-seg-btn">Open ↗</a>
                @endif
            </div>
        </div>

        @if ($saved)
            @php
                $frame = match ($previewDevice) {
                    'tablet' => ['w' => 820, 'scale' => 0.62],
                    'mobile' => ['w' => 390, 'scale' => 0.9],
                    default => ['w' => 1280, 'scale' => 0.52],
                };
                $frameH = (int) ceil(520 / $frame['scale']);
            @endphp
            <div class="relative flex justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-gray-800 dark:bg-gray-950"
                 style="height: 520px" wire:loading.class="opacity-60">
                <iframe wire:key="preview-frame-{{ $previewNonce }}"
                        src="{{ $page->path() }}?preview=1&v={{ $previewNonce }}"
                        title="Draft preview ({{ $previewDevice }})"
                        class="origin-top border-0 bg-white shadow-sm"
                        style="width: {{ $frame['w'] }}px; height: {{ $frameH }}px; transform: scale({{ $frame['scale'] }});"
                        sandbox="allow-same-origin allow-scripts"></iframe>
                <p class="pointer-events-none absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-navy-900/80 px-3 py-1 text-[11px] whitespace-nowrap text-white">
                    {{ $previewStale ? 'Updating the preview…' : 'Draft preview — click a section to edit it' }}
                </p>
            </div>
        @else
            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-10 text-center dark:border-gray-700">
                <p class="text-theme-sm font-medium text-gray-700 dark:text-gray-200">The preview writes itself</p>
                <p class="mx-auto mt-1 max-w-md text-theme-sm text-gray-500 dark:text-gray-400">{{ $previewNotice !== '' ? $previewNotice : 'Start with a title and a URL. The page saves itself as a draft and appears here — before you ever press Save.' }}</p>
            </div>
        @endif

        @if ($saved)
            <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                @if ($shareUrl === '')
                    <button type="button" wire:click="makeShareLink" class="text-theme-sm text-brand-600 hover:underline">Make a shareable preview link (valid 24 h)</button>
                @else
                    <p class="wx-label">Shareable for 24 hours — no login needed:</p>
                    <div class="flex items-center gap-2" x-data>
                        <input type="text" readonly value="{{ $shareUrl }}" x-ref="link" class="wx-input text-theme-xs" onfocus="this.select()">
                        <button type="button" class="wx-seg-btn shrink-0" @click="navigator.clipboard.writeText($refs.link.value)">Copy</button>
                    </div>
                @endif
            </div>
        @endif

        <script>
            // Click-to-edit (V2-3): the preview's editor tools postMessage the clicked section's
            // key; we hand it to this component, which scrolls to its form. The key is checked
            // against the open page server-side, so a message from another frame edits nothing.
            if (window.__wxClickToEdit === undefined) {
                window.__wxClickToEdit = true; // one listener, however often Livewire redraws
                window.addEventListener('message', function (event) {
                    var data = event.data;
                    if (!data || data.type !== 'wx-edit-section' || typeof data.key !== 'string') {
                        return;
                    }

                    @this.call('editSectionFromPreview', data.key);
                });

                // Undo / redo (19 Oct, admin v2.1 pro parity): Ctrl+Z, Ctrl+Y and Ctrl+Shift+Z take a
                // structural change back. Inside a field the browser's own text undo wins — which is
                // exactly what someone typing expects.
                window.addEventListener('keydown', function (event) {
                    var target = event.target;

                    if (target && typeof target.closest === 'function' && target.closest('input, textarea, select, [contenteditable]')) {
                        return;
                    }

                    if (!(event.ctrlKey || event.metaKey)) {
                        return;
                    }

                    var key = String(event.key).toLowerCase();

                    if (key === 'z') {
                        event.preventDefault();
                        @this.call(event.shiftKey ? 'redo' : 'undo');
                    } else if (key === 'y') {
                        event.preventDefault();
                        @this.call('redo');
                    }
                });
            }
        </script>
    </x-admin.card>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-admin.card title="Page">
                <div class="space-y-4">
                    <x-admin.field label="Title" for="title" hint="Shown as the heading and in search results.">
                        <input id="title" type="text" wire:model.live.debounce.700ms="title" class="wx-input" placeholder="Interior Design in Lahore">
                    </x-admin.field>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-admin.field label="URL" for="slug" hint="Always with a slash at both ends. Changing it breaks links: add a redirect under Website → Redirects.">
                            <input id="slug" type="text" wire:model.live.debounce.700ms="slug" class="wx-input" placeholder="/interior-design/">
                        </x-admin.field>
                        <x-admin.field label="Type" for="type">
                            <select id="type" wire:model.live="type" class="wx-input">
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>
                    </div>

                    <x-admin.field label="Summary" for="excerpt" hint="One or two sentences. Search engines and the links to this page use it.">
                        <textarea id="excerpt" rows="2" wire:model.live.debounce.700ms="excerpt" class="wx-input" placeholder="Design, drawings and site supervision for homes and workplaces."></textarea>
                    </x-admin.field>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-admin.field label="Sort order" for="sort" hint="Lower numbers come first in menus.">
                            <input id="sort" type="number" min="0" max="9999" wire:model.live.debounce.700ms="sort" class="wx-input">
                        </x-admin.field>
                        <x-admin.field label="Status" for="status">
                            <input id="status" type="text" value="{{ \App\Domain\Website\Enums\PageStatus::from($status)->label() }}" class="wx-input" disabled>
                        </x-admin.field>
                    </div>
                </div>
            </x-admin.card>

            @if (! $isCanvas)
                <x-admin.card title="Campaign canvas" description="For landing pages only (D-107): switch this page to hand-placed HTML, free of the theme's sections. Clearly marked in the Pages list; switch back any time — the sections wait underneath.">
                    <button type="button" wire:click="toggleCanvas" class="wx-mini-btn">Switch to the campaign canvas</button>
                </x-admin.card>
            @endif

            @if ($isCanvas)
                <x-admin.card title="Campaign canvas" description="This page renders hand-placed HTML — free of the theme's sections, scripts stripped on save. The sections underneath wait untouched; switch back any time.">
                    <textarea wire:model="canvasHtml" rows="20" class="wx-input font-mono text-theme-xs" spellcheck="false" aria-label="Campaign HTML"></textarea>
                    <p class="mt-2 text-theme-xs text-gray-400">Saved as-is except &lt;script&gt;, on-event attributes and javascript: links — those are removed at the door.</p>
                    <div class="mt-3">
                        <button type="button" wire:click="toggleCanvas" class="wx-mini-btn">Back to the structured sections</button>
                    </div>
                </x-admin.card>
            @endif
            @if (! $isCanvas)
            <x-admin.card title="Section library" description="Click a section to add it to the page below — the studio's shelves, in reading order.">
                <div class="space-y-4">
                    @foreach ($library as $category => $entries)
                        <div>
                            <p class="mb-2 text-theme-xs font-semibold tracking-wider text-gray-400 uppercase">{{ $category }}</p>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($entries as $entry)
                                    <button type="button" wire:click="addBlock('{{ $entry['value'] }}')"
                                            class="group rounded-xl border border-gray-200 p-3 text-left transition hover:border-brand-500 hover:bg-brand-50/50 dark:border-gray-800 dark:hover:border-brand-500/60 dark:hover:bg-white/5">
                                        <span class="flex items-center justify-between gap-2">
                                            <span class="text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ $entry['label'] }}</span>
                                            <span class="text-gray-300 transition group-hover:text-brand-600" aria-hidden="true">+</span>
                                        </span>
                                        <span class="mt-0.5 block text-theme-xs text-gray-500">{{ $entry['description'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div>
                        <p class="mb-2 text-theme-xs font-semibold tracking-wider text-gray-400 uppercase">Saved by the studio</p>
                        @forelse ($savedSections as $saved)
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-dashed border-gray-200 px-3 py-2 dark:border-gray-800">
                                <div class="min-w-0">
                                    <p class="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ $saved->name }}</p>
                                    <p class="text-theme-xs text-gray-500">{{ $saved->type->label() }} · saved {{ $saved->created_at?->format('d M Y') }} by {{ $saved->creator?->name ?? '—' }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button type="button" wire:click="insertSavedSection({{ $saved->id }})" class="wx-mini-btn" title="Add to this page">Insert</button>
                                    <button type="button" wire:click="deleteSavedSection({{ $saved->id }})" wire:confirm="Remove “{{ $saved->name }}” from the library?" class="wx-mini-btn text-error-600" aria-label="Delete saved section">✕</button>
                                </div>
                            </div>
                        @empty
                            <p class="text-theme-xs text-gray-400">Nothing saved yet. Configure a section below and press “Save as section” — it lands here for every page.</p>
                        @endforelse
                    </div>
                </div>
            </x-admin.card>
            @endif

            @if (! $isCanvas)
            {{-- Layers (19 Oct, admin v2.1 pro parity): the page at a glance, without the forms. A row
                 jumps to its section; the arrows move it; the eye hides it. The search box filters the
                 list only — the page is never filtered. --}}
            <x-admin.card title="Layers" description="The page from top to bottom. Click a row to open its form, move it with the arrows, or hide it without deleting anything.">
                @php
                    $needle = mb_strtolower(trim($layerQuery));
                    $layers = collect($blocks)
                        ->map(fn (array $block, int $index): array => [
                            'index' => $index,
                            'block' => $block,
                            'type' => BlockType::from($block['type']),
                            'name' => \App\Domain\Website\Support\SectionStyle::title($block['settings'] ?? [], BlockType::from($block['type'])->label()),
                        ])
                        ->filter(fn (array $layer): bool => $needle === ''
                            || str_contains(mb_strtolower($layer['name'].' '.$layer['type']->label()), $needle))
                        ->values();
                @endphp

                @if ($blocks !== [])
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <input type="search" wire:model.live.debounce.300ms="layerQuery" class="wx-input sm:max-w-xs"
                               placeholder="Search sections (name or type)…" aria-label="Search sections">
                        <span class="text-theme-xs text-gray-500">{{ count($blocks) }} {{ \Illuminate\Support\Str::plural('section', count($blocks)) }}</span>
                    </div>

                    @if ($layers->isEmpty())
                        <p class="text-theme-sm text-gray-500">No section matches “{{ $layerQuery }}”.</p>
                    @else
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($layers as $layer)
                                @php
                                    $index = $layer['index'];
                                    $block = $layer['block'];
                                    $blockType = $layer['type'];
                                    $shown = $block['visible'] ?? true;
                                @endphp
                                <li wire:key="layer-{{ $block['key'] }}" class="flex items-center gap-2 py-2">
                                    <span class="w-6 shrink-0 text-theme-xs text-gray-400">{{ $index + 1 }}</span>
                                    <button type="button" wire:click="focusBlock({{ $index }})" class="min-w-0 flex-1 text-left">
                                        <span @class(['block truncate text-theme-sm font-medium', 'text-gray-800 dark:text-white/90' => $shown, 'text-gray-400 line-through dark:text-gray-500' => ! $shown])>{{ $layer['name'] }}</span>
                                        <span class="block text-theme-xs text-gray-500">
                                            @if ($layer['name'] !== $blockType->label()){{ $blockType->label() }} · @endif{{ $shown ? 'visible' : 'hidden' }}
                                        </span>
                                    </button>
                                    <button type="button" wire:click="moveBlock({{ $index }}, -1)" class="wx-mini-btn" aria-label="Move up" title="Move up" @disabled($index === 0)>
                                        <span aria-hidden="true">↑</span>
                                    </button>
                                    <button type="button" wire:click="moveBlock({{ $index }}, 1)" class="wx-mini-btn" aria-label="Move down" title="Move down" @disabled($index === count($blocks) - 1)>
                                        <span aria-hidden="true">↓</span>
                                    </button>
                                    <button type="button" wire:click="toggleBlock({{ $index }})" class="wx-mini-btn"
                                            aria-label="{{ $shown ? 'Hide' : 'Show' }} section" title="{{ $shown ? 'Hide' : 'Show' }}">
                                        <x-icon :name="$shown ? 'eye' : 'eye-slash'" class="size-4" />
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <p class="text-theme-sm text-gray-500">Nothing on the page yet — add a section below and it appears here.</p>
                @endif
            </x-admin.card>

            <x-admin.card title="Sections" description="Drag the handle to reorder. One section per card, in the order visitors read them. A section you untick keeps its content but stops showing.">
                <x-slot:actions>
                    <button type="button" wire:click="undo" class="wx-seg-btn" title="Undo the last change (Ctrl+Z)" @disabled(! $canUndo)>
                        <x-icon name="undo" class="size-3.5" /> Undo
                    </button>
                    <button type="button" wire:click="redo" class="wx-seg-btn" title="Redo (Ctrl+Y)" @disabled(! $canRedo)>
                        <x-icon name="redo" class="size-3.5" /> Redo
                    </button>
                </x-slot:actions>

                <div class="space-y-4" wire:sort="sortBlocks">
                    @forelse ($blocks as $index => $block)
                        @php $blockType = BlockType::from($block['type']); @endphp
                        <div wire:key="blk-{{ $block['key'] }}" id="blk-{{ $block['key'] }}" wire:sort:item="{{ $block['key'] }}" @class([
                            'rounded-xl border transition',
                            'border-gray-200 dark:border-gray-800' => $block['visible'] ?? true,
                            'border-dashed border-gray-300 opacity-70' => ! ($block['visible'] ?? true),
                            'border-brand-500 ring-2 ring-brand-500/30' => $block['key'] === $activeBlock,
                        ])>
                            <header class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-2.5 dark:border-gray-800">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span wire:sort:handle class="section-handle cursor-grab select-none text-gray-300 hover:text-gray-500 active:cursor-grabbing" title="Drag to reorder" aria-hidden="true">⠿</span>
                                    <span class="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ \App\Domain\Website\Support\SectionStyle::title($block['settings'] ?? [], $blockType->label()) }}</span>
                                    <label class="flex shrink-0 items-center gap-1.5 text-theme-xs text-gray-500">
                                        <input type="checkbox" wire:click="toggleBlock({{ $index }})" @checked($block['visible'] ?? true) class="rounded border-gray-300"> visible
                                    </label>
                                </div>
                                <div class="flex shrink-0 items-center gap-1" wire:sort:ignore>
                                    <button type="button" wire:click="moveBlock({{ $index }}, -1)" class="wx-mini-btn" aria-label="Move up" @if ($index === 0) disabled @endif>↑</button>
                                    <button type="button" wire:click="moveBlock({{ $index }}, 1)" class="wx-mini-btn" aria-label="Move down" @if ($index === count($blocks) - 1) disabled @endif>↓</button>
                                    <button type="button" wire:click="duplicateBlock({{ $index }})" class="wx-mini-btn" title="Duplicate this section" aria-label="Duplicate section">⧉</button>
                                    <button type="button" wire:click="openSectionModal({{ $index }})" class="wx-mini-btn" title="Save to the library for reuse" aria-label="Save as section">★</button>
                                    <button type="button" wire:click="removeBlock({{ $index }})" class="wx-mini-btn text-error-600" aria-label="Delete section">✕</button>
                                </div>
                            </header>

                            @php $style = $block['settings'] ?? \App\Domain\Website\Support\SectionStyle::defaults(); @endphp
                            <div class="grid grid-cols-1 gap-2 border-b border-gray-100 bg-gray-50/70 px-4 py-3 sm:grid-cols-2 lg:grid-cols-3 dark:border-gray-800 dark:bg-white/[0.02]">
                                <label class="block">
                                    <span class="wx-label">Section name <span class="font-normal text-gray-400">(in the editor)</span></span>
                                    <input type="text" wire:model.live.debounce.700ms="blocks.{{ $index }}.settings.label" class="wx-input" placeholder="{{ $blockType->label() }}">
                                </label>
                                <label class="block">
                                    <span class="wx-label">Background</span>
                                    <select wire:model.live="blocks.{{ $index }}.settings.background" class="wx-input">
                                        @foreach (\App\Domain\Website\Support\SectionStyle::BACKGROUNDS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="wx-label">Spacing</span>
                                    <select wire:model.live="blocks.{{ $index }}.settings.spacing" class="wx-input">
                                        @foreach (\App\Domain\Website\Support\SectionStyle::SPACINGS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="wx-label">Content width</span>
                                    <select wire:model.live="blocks.{{ $index }}.settings.width" class="wx-input">
                                        @foreach (\App\Domain\Website\Support\SectionStyle::WIDTHS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="wx-label">Alignment</span>
                                    <select wire:model.live="blocks.{{ $index }}.settings.align" class="wx-input">
                                        @foreach (\App\Domain\Website\Support\SectionStyle::ALIGNMENTS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <p class="self-end text-theme-xs text-gray-400">The page follows these as you change them.</p>
                            </div>

                            <div class="space-y-3 p-4">
                                @foreach ($blockType->schema() as $field)
                                    @php $path = 'blocks.'.$index.'.data.'.$field['key']; @endphp
                                    @switch($field['type'])
                                        @case('text')
                                            <x-admin.field :label="$field['label']">
                                                <input type="text" wire:model.live.debounce.700ms="{{ $path }}" class="wx-input">
                                            </x-admin.field>
                                            @break
                                        @case('textarea')
                                            <x-admin.field :label="$field['label']">
                                                <textarea rows="{{ $field['rows'] ?? 3 }}" wire:model.live.debounce.700ms="{{ $path }}" class="wx-input"></textarea>
                                            </x-admin.field>
                                            @break
                                        @case('code')
                                            <x-admin.field :label="$field['label']" hint="Your own markup. Scripts, on-event attributes (onclick…) and javascript: links are removed when the section is saved.">
                                                <textarea rows="{{ $field['rows'] ?? 12 }}" wire:model.live.debounce.700ms="{{ $path }}" class="wx-input font-mono text-theme-xs" spellcheck="false" placeholder="&lt;section&gt;…&lt;/section&gt;"></textarea>
                                            </x-admin.field>
                                            @break
                                        @case('bool')
                                            <label class="flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300">
                                                <input type="checkbox" wire:model.live="{{ $path }}" class="rounded border-gray-300"> {{ $field['label'] }}
                                            </label>
                                            @break
                                        @case('image')
                                            <x-admin.field :label="$field['label']" hint="Picked from Website → Media. Alt text is set there.">
                                                <select wire:model.live="{{ $path }}" class="wx-input">
                                                    <option value="">No image</option>
                                                    @foreach ($media as $image)
                                                        <option value="{{ $image->id }}">{{ $image->title ?: basename($image->path) }}</option>
                                                    @endforeach
                                                </select>
                                            </x-admin.field>
                                            @break
                                        @case('library')
                                            @php
                                                $libraryEntries = \App\Domain\Website\Services\ContentLibrary::entries($field['kind'] ?? 'faq');
                                                $libLabel = $field['label'];
                                                $libHint = 'Hold Ctrl (Cmd on Mac) to pick more than one. Managed in Website → Content — the page shows them in the library order.';
                                            @endphp
                                            <x-admin.field :label="$libLabel" :hint="$libHint">
                                                <select multiple size="4" wire:model.live.debounce.700ms="{{ $path }}" class="wx-input" @if ($libraryEntries === []) disabled @endif>
                                                    @foreach ($libraryEntries as $libraryEntry)
                                                        <option value="{{ $libraryEntry->id }}">{{ $libraryEntry->label() }}</option>
                                                    @endforeach
                                                </select>
                                                @if ($libraryEntries === [])
                                                    <p class="mt-1 text-theme-xs text-gray-400">The library is empty — add entries under Website → Content first.</p>
                                                @endif
                                            </x-admin.field>
                                            @break
                                        @case('pages')
                                            <x-admin.field :label="$field['label']" hint="Hold Ctrl (Cmd on Mac) to pick more than one.">
                                                <select multiple size="4" wire:model.live.debounce.700ms="{{ $path }}" class="wx-input">
                                                    @foreach ($pages as $option)
                                                        <option value="{{ $option->id }}">{{ $option->title }} · {{ $option->slug }}</option>
                                                    @endforeach
                                                </select>
                                            </x-admin.field>
                                            @break
                                        @case('repeater')
                                            <div class="rounded-lg border border-gray-100 p-3 dark:border-gray-800">
                                                <p class="mb-2 text-theme-xs font-medium tracking-wide text-gray-500 uppercase">{{ $field['label'] }}</p>
                                                <div class="space-y-3">
                                                    @foreach ($block['data'][$field['key']] ?? [] as $position => $item)
                                                        <div wire:key="item-{{ $index }}-{{ $field['key'] }}-{{ $position }}" class="flex items-start gap-2">
                                                            <div class="grid flex-1 grid-cols-1 gap-2 md:grid-cols-2">
                                                                @foreach ($field['fields'] as $sub)
                                                                    <div @class(['md:col-span-2' => ($sub['type'] ?? 'text') === 'textarea'])>
                                                                        <label class="wx-label">{{ $sub['label'] }}</label>
                                                                        @if (($sub['type'] ?? 'text') === 'textarea')
                                                                            <textarea rows="{{ $sub['rows'] ?? 2 }}" wire:model.live.debounce.700ms="{{ $path }}.{{ $position }}.{{ $sub['key'] }}" class="wx-input"></textarea>
                                                                        @elseif (($sub['type'] ?? 'text') === 'image')
                                                                            <select wire:model.live="{{ $path }}.{{ $position }}.{{ $sub['key'] }}" class="wx-input">
                                                                                <option value="">No image</option>
                                                                                @foreach ($media as $image)
                                                                                    <option value="{{ $image->id }}">{{ $image->title ?: basename($image->path) }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        @else
                                                                            <input type="text" wire:model.live.debounce.700ms="{{ $path }}.{{ $position }}.{{ $sub['key'] }}" class="wx-input">
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                            <button type="button" wire:click="removeItem({{ $index }}, '{{ $field['key'] }}', {{ $position }})" class="wx-mini-btn mt-6 text-error-600" aria-label="Remove row">✕</button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <button type="button" wire:click="addItem({{ $index }}, '{{ $field['key'] }}')" class="mt-3 text-theme-sm text-brand-600 hover:underline">+ Add {{ \Illuminate\Support\Str::lower($field['label']) }}</button>
                                            </div>
                                            @break
                                    @endswitch
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <x-admin.empty title="No sections yet" description="Add a hero or an intro section first, then the rest." />
                    @endforelse
                </div>
            </x-admin.card>
        </div>

        <div class="space-y-6">
            @endif

            <x-admin.card title="Templates" description="A layout for this kind of page: which sections, in what order. Applying replaces the sections below — nothing is saved until you press Save draft.">
                <div class="space-y-2">
                    @foreach ($templates as $template)
                        <div class="flex items-start justify-between gap-3 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-800">
                            <div class="min-w-0">
                                <p class="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                    {{ $template->name }}
                                    @if ($template->is_default)<span class="ml-1 rounded-full bg-brand-50 px-1.5 py-0.5 text-[10px] text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">Default</span>@endif
                                </p>
                                <p class="text-theme-xs text-gray-500">{{ $template->page_type->label() }} · {{ count($template->sections) }} sections</p>
                            </div>
                            <button type="button" wire:click="applyTemplate({{ $template->id }})"
                                    wire:confirm="Replace this page's sections with “{{ $template->name }}”? Your current sections are not saved anywhere until Save draft."
                                    class="wx-mini-btn shrink-0">Apply</button>
                        </div>
                    @endforeach
                    <button type="button" wire:click="$set('showTemplateModal', true)"
                            class="mt-1 text-theme-sm text-brand-600 hover:underline">Save this page's layout as a template…</button>
                </div>
            </x-admin.card>

            <x-admin.card title="Search engine" description="What search engines read, and the check the builder runs on it.">
                <div class="mb-5 rounded-xl border border-gray-200 bg-gray-50/70 p-3 dark:border-gray-800 dark:bg-white/[0.02]">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-theme-sm font-medium text-gray-800 dark:text-white/90">Page check</p>
                        @if ($audit === [])
                            <span class="rounded-full bg-success-50 px-2 py-0.5 text-theme-xs text-success-700 dark:bg-success-500/10 dark:text-success-500">Everything checks out</span>
                        @else
                            <span class="rounded-full bg-warning-50 px-2 py-0.5 text-theme-xs text-warning-700 dark:bg-warning-500/10 dark:text-warning-500">{{ count($audit) }} to look at</span>
                        @endif
                    </div>
                    @if ($audit !== [])
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($audit as $finding)
                                <li class="flex items-start gap-2 text-theme-sm">
                                    <span @class([
                                        'mt-0.5 inline-flex size-4 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold',
                                        'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-500' => $finding['level'] === 'bad',
                                        'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-500' => $finding['level'] !== 'bad',
                                    ]) aria-hidden="true">{{ $finding['level'] === 'bad' ? '!' : '·' }}</span>
                                    <span class="text-gray-600 dark:text-gray-300"><span class="font-medium text-gray-500">{{ $finding['area'] }}:</span> {{ $finding['message'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="space-y-4">
                    <x-admin.field label="SEO title" for="seoTitle" hint="{{ strlen($seoTitle) }}/60 · how it appears as the blue link in Google.">
                        <input id="seoTitle" type="text" wire:model.live.debounce.700ms="seoTitle" class="wx-input" placeholder="Interior Design in Lahore | Woodex Interior">
                    </x-admin.field>
                    <x-admin.field label="Meta description" for="seoDescription" hint="{{ strlen($seoDescription) }} characters · aim for 120–165.">
                        <textarea id="seoDescription" rows="3" wire:model.live.debounce.700ms="seoDescription" class="wx-input"></textarea>
                    </x-admin.field>
                    <x-admin.field label="Focus keyword" for="focusKeyword" hint="The search this page should answer.">
                        <input id="focusKeyword" type="text" wire:model.live.debounce.700ms="focusKeyword" class="wx-input" placeholder="interior design Lahore">
                    </x-admin.field>
                    <x-admin.field label="Robots" for="seoRobots" hint="Leave empty to let search engines index the page.">
                        <select id="seoRobots" wire:model.live.debounce.700ms="seoRobots" class="wx-input">
                            <option value="">Index this page (default)</option>
                            <option value="noindex">noindex — keep it out of search results</option>
                            <option value="noindex, nofollow">noindex, nofollow</option>
                        </select>
                    </x-admin.field>
                    <x-admin.field label="Social image" for="ogImageId" hint="Shown when the page is shared (1200 × 630).">
                        <select id="ogImageId" wire:model.live.debounce.700ms="ogImageId" class="wx-input">
                            <option value="">Default studio image</option>
                            @foreach ($media as $image)
                                <option value="{{ $image->id }}">{{ $image->title ?: basename($image->path) }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                </div>
            </x-admin.card>

            @if ($saved)
                <x-admin.card title="Publishing">
                    <dl class="space-y-2 text-theme-sm">
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Status</dt><dd class="font-medium">{{ PageStatus::from($status)->label() }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Live at</dt><dd class="truncate"><a href="{{ $page->canonical() }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">{{ $page->path() }}</a></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Last changed</dt><dd>{{ $page->updated_at?->format('d M Y, H:i') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Sections</dt><dd>{{ count($blocks) }}</dd></div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($isPublished)
                            <x-admin.button variant="secondary" icon="eye-off" wire:click="unpublish">Take offline</x-admin.button>
                        @else
                            <x-admin.button variant="ghost" :href="$page->canonical().'?preview=1'" target="_blank" rel="noopener">Preview draft</x-admin.button>
                        @endif
                    </div>
                </x-admin.card>

                <x-admin.card title="History" description="Every publish is kept. Restoring puts the page back exactly as it was.">
                    @if ($page->versions->isEmpty())
                        <p class="text-theme-sm text-gray-500">Nothing published yet.</p>
                    @else
                        <ul class="space-y-2 text-theme-sm">
                            @foreach ($page->versions->take(8) as $version)
                                <li class="flex items-center justify-between gap-3">
                                    <span class="text-gray-600 dark:text-gray-300">
                                        {{ $version->created_at?->format('d M Y, H:i') }}
                                        <span class="text-theme-xs text-gray-500">· {{ $version->user?->name ?? 'import' }}</span>
                                    </span>
                                    <button type="button" wire:click="restore({{ $version->id }})" class="text-theme-sm text-brand-600 hover:underline">Restore</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-admin.card>
            @endif
        </div>
    </div>

    @if ($showSectionModal)
        <x-admin.modal name="showSectionModal" title="Save as a section" description="It lands in the library, ready to drop onto any page — content and all." submit="storeSection">
            <x-admin.field label="Name" for="sectionName">
                <input id="sectionName" type="text" wire:model="sectionName" class="wx-input" placeholder="Signature hero">
            </x-admin.field>
        </x-admin.modal>
    @endif
    {{-- V2-3 F5: save this page's layout as a template. --}}
    @if ($showTemplateModal)
        <x-admin.modal name="showTemplateModal" title="Save as a page template" description="The sections below, in their order, become the starting layout for future pages — the photos are not copied." submit="saveAsTemplate">
            <x-admin.field label="Template name" for="templateName">
                <input id="templateName" type="text" wire:model="templateName" class="wx-input" placeholder="Service page — our layout" required>
            </x-admin.field>
            <x-slot:footer>
                <x-admin.button type="submit" icon="save">Save template</x-admin.button>
            </x-slot:footer>
        </x-admin.modal>
    @endif
</div>
