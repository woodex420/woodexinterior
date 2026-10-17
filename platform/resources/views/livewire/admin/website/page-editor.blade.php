@use('App\Domain\Website\Enums\BlockType')
@use('App\Domain\Website\Enums\PageStatus')

@php
    $isPublished = $status === \App\Domain\Website\Enums\PageStatus::Published->value;
    $canPublish = auth()->user()->can('website.publish');
    $saved = $page !== null;
@endphp

<div>
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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-admin.card title="Page">
                <div class="space-y-4">
                    <x-admin.field label="Title" for="title" hint="Shown as the heading and in search results.">
                        <input id="title" type="text" wire:model.blur="title" class="wx-input" placeholder="Interior Design in Lahore">
                    </x-admin.field>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-admin.field label="URL" for="slug" hint="Always with a slash at both ends. Changing it breaks links: add a redirect under Website → SEO & Redirects.">
                            <input id="slug" type="text" wire:model.blur="slug" class="wx-input" placeholder="/interior-design/">
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
                        <textarea id="excerpt" rows="2" wire:model.blur="excerpt" class="wx-input" placeholder="Design, drawings and site supervision for homes and workplaces."></textarea>
                    </x-admin.field>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-admin.field label="Sort order" for="sort" hint="Lower numbers come first in menus.">
                            <input id="sort" type="number" min="0" max="9999" wire:model.blur="sort" class="wx-input">
                        </x-admin.field>
                        <x-admin.field label="Status" for="status">
                            <input id="status" type="text" value="{{ \App\Domain\Website\Enums\PageStatus::from($status)->label() }}" class="wx-input" disabled>
                        </x-admin.field>
                    </div>
                </div>
            </x-admin.card>

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

            <x-admin.card title="Sections" description="Drag the handle to reorder. One section per card, in the order visitors read them. A section you untick keeps its content but stops showing.">
                <div class="space-y-4" wire:sort="sortBlocks" wire:sort.options="{ handle: '.section-handle', animation: 150 }">
                    @forelse ($blocks as $index => $block)
                        @php $blockType = BlockType::from($block['type']); @endphp
                        <div wire:key="blk-{{ $block['key'] }}" @class([
                            'rounded-xl border',
                            'border-gray-200 dark:border-gray-800' => $block['visible'] ?? true,
                            'border-dashed border-gray-300 opacity-70' => ! ($block['visible'] ?? true),
                        ])>
                            <header class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-2.5 dark:border-gray-800">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="section-handle cursor-grab select-none text-gray-300 hover:text-gray-500 active:cursor-grabbing" title="Drag to reorder" aria-hidden="true">⠿</span>
                                    <span class="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ $blockType->label() }}</span>
                                    <label class="flex shrink-0 items-center gap-1.5 text-theme-xs text-gray-500">
                                        <input type="checkbox" wire:click="toggleBlock({{ $index }})" @checked($block['visible'] ?? true) class="rounded border-gray-300"> visible
                                    </label>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button type="button" wire:click="moveBlock({{ $index }}, -1)" class="wx-mini-btn" aria-label="Move up" @if ($index === 0) disabled @endif>↑</button>
                                    <button type="button" wire:click="moveBlock({{ $index }}, 1)" class="wx-mini-btn" aria-label="Move down" @if ($index === count($blocks) - 1) disabled @endif>↓</button>
                                    <button type="button" wire:click="duplicateBlock({{ $index }})" class="wx-mini-btn" title="Duplicate this section" aria-label="Duplicate section">⧉</button>
                                    <button type="button" wire:click="openSectionModal({{ $index }})" class="wx-mini-btn" title="Save to the library for reuse" aria-label="Save as section">★</button>
                                    <button type="button" wire:click="removeBlock({{ $index }})" class="wx-mini-btn text-error-600" aria-label="Delete section">✕</button>
                                </div>
                            </header>

                            <div class="space-y-3 p-4">
                                @foreach ($blockType->schema() as $field)
                                    @php $path = 'blocks.'.$index.'.data.'.$field['key']; @endphp
                                    @switch($field['type'])
                                        @case('text')
                                            <x-admin.field :label="$field['label']">
                                                <input type="text" wire:model.blur="{{ $path }}" class="wx-input">
                                            </x-admin.field>
                                            @break
                                        @case('textarea')
                                            <x-admin.field :label="$field['label']">
                                                <textarea rows="{{ $field['rows'] ?? 3 }}" wire:model.blur="{{ $path }}" class="wx-input"></textarea>
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
                                        @case('pages')
                                            <x-admin.field :label="$field['label']" hint="Hold Ctrl (Cmd on Mac) to pick more than one.">
                                                <select multiple size="4" wire:model.blur="{{ $path }}" class="wx-input">
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
                                                                            <textarea rows="{{ $sub['rows'] ?? 2 }}" wire:model.blur="{{ $path }}.{{ $position }}.{{ $sub['key'] }}" class="wx-input"></textarea>
                                                                        @elseif (($sub['type'] ?? 'text') === 'image')
                                                                            <select wire:model.live="{{ $path }}.{{ $position }}.{{ $sub['key'] }}" class="wx-input">
                                                                                <option value="">No image</option>
                                                                                @foreach ($media as $image)
                                                                                    <option value="{{ $image->id }}">{{ $image->title ?: basename($image->path) }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        @else
                                                                            <input type="text" wire:model.blur="{{ $path }}.{{ $position }}.{{ $sub['key'] }}" class="wx-input">
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
            @if ($saved)
                <x-admin.card title="Preview" description="How the page looks — saved changes only; press Save draft first.">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="setPreviewDevice('{{ $previewDevice === 'desktop' ? '' : 'desktop' }}')"
                                @class(['wx-mini-btn' => true, 'border-brand-500 text-brand-700 dark:text-brand-300' => $previewDevice === 'desktop'])>Desktop</button>
                        <button type="button" wire:click="setPreviewDevice('{{ $previewDevice === 'tablet' ? '' : 'tablet' }}')"
                                @class(['wx-mini-btn' => true, 'border-brand-500 text-brand-700 dark:text-brand-300' => $previewDevice === 'tablet'])>Tablet</button>
                        <button type="button" wire:click="setPreviewDevice('{{ $previewDevice === 'mobile' ? '' : 'mobile' }}')"
                                @class(['wx-mini-btn' => true, 'border-brand-500 text-brand-700 dark:text-brand-300' => $previewDevice === 'mobile'])>Mobile</button>
                        <a href="{{ $page->path() }}?preview=1" target="_blank" rel="noopener" class="wx-mini-btn">Open ↗</a>
                    </div>

                    @if ($previewDevice !== '')
                        @php
                            $frame = match ($previewDevice) {
                                'tablet' => ['w' => 820, 'scale' => 0.42],
                                'mobile' => ['w' => 390, 'scale' => 0.92],
                                default => ['w' => 1280, 'scale' => 0.28],
                            };
                            $frameH = (int) ceil(420 / $frame['scale']);
                        @endphp
                        <div class="mt-3 h-[420px] overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950" wire:loading.class="opacity-50">
                            <iframe src="{{ $page->path() }}?preview=1" title="Draft preview ({{ $previewDevice }})"
                                    class="origin-top-left border-0 bg-white"
                                    style="width: {{ $frame['w'] }}px; height: {{ $frameH }}px; transform: scale({{ $frame['scale'] }});"
                                    sandbox="allow-same-origin"></iframe>
                        </div>
                    @endif

                    <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                        @if ($shareUrl === '')
                            <button type="button" wire:click="makeShareLink" class="text-theme-sm text-brand-600 hover:underline">Make a shareable preview link (valid 24 h)</button>
                        @else
                            <p class="wx-label">Shareable for 24 hours — no login needed:</p>
                            <div class="flex items-center gap-2" x-data>
                                <input type="text" readonly value="{{ $shareUrl }}" x-ref="link" class="wx-input text-theme-xs" onfocus="this.select()">
                                <button type="button" class="wx-mini-btn shrink-0" @click="navigator.clipboard.writeText($refs.link.value)">Copy</button>
                            </div>
                        @endif
                    </div>
                </x-admin.card>
            @endif

            <x-admin.card title="Search engine">
                <div class="space-y-4">
                    <x-admin.field label="SEO title" for="seoTitle" hint="{{ strlen($seoTitle) }}/60 · how it appears as the blue link in Google.">
                        <input id="seoTitle" type="text" wire:model.blur="seoTitle" class="wx-input" placeholder="Interior Design in Lahore | Woodex Interior">
                    </x-admin.field>
                    <x-admin.field label="Meta description" for="seoDescription" hint="{{ strlen($seoDescription) }} characters · aim for 120–165.">
                        <textarea id="seoDescription" rows="3" wire:model.blur="seoDescription" class="wx-input"></textarea>
                    </x-admin.field>
                    <x-admin.field label="Focus keyword" for="focusKeyword" hint="The search this page should answer.">
                        <input id="focusKeyword" type="text" wire:model.blur="focusKeyword" class="wx-input" placeholder="interior design Lahore">
                    </x-admin.field>
                    <x-admin.field label="Robots" for="seoRobots" hint="Leave empty to let search engines index the page.">
                        <select id="seoRobots" wire:model.blur="seoRobots" class="wx-input">
                            <option value="">Index this page (default)</option>
                            <option value="noindex">noindex — keep it out of search results</option>
                            <option value="noindex, nofollow">noindex, nofollow</option>
                        </select>
                    </x-admin.field>
                    <x-admin.field label="Social image" for="ogImageId" hint="Shown when the page is shared (1200 × 630).">
                        <select id="ogImageId" wire:model.blur="ogImageId" class="wx-input">
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
</div>
