<div>
    <x-admin.page-header title="Hero slides" :description="$fromHero ? 'The home page\'s opening hero — the first thing every visitor sees. It is a single hero today; edit its photograph, words and buttons here, or add a second slide to make it rotate. The page builder shows exactly the same thing.' : 'The home page\'s opening slider — the first thing every visitor sees. Each slide is a photograph, a few words and buttons; they rotate on their own. This edits the home page\'s real hero section: the page builder shows the same slides.'"
                         :breadcrumbs="['Website' => null, 'Hero slides' => null]">
        <x-slot:actions>
            <x-admin.button variant="secondary" icon="external" href="{{ route('admin.website.pages.edit', $homeId) }}">Open the full builder</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3">
        <x-admin.stat label="Slides" :value="count($slides)" icon="image" />
        <x-admin.stat label="Home page" :value="$homePublished ? 'Published' : 'Draft'" icon="globe" :tone="$homePublished ? 'wood' : 'warning'" />
        <x-admin.stat label="Last published version" :value="$versions->first()?->created_at?->format('d M Y') ?? '—'" icon="clock" />
    </div>

    @if ($homeId === 0)
        <x-admin.card title="No home page yet">
            <x-admin.empty icon="alert" title="There is no home page to hold a hero" description="The hero slides live on the home page. Create it in All pages first — then come back here and the slider is one click away." />
        </x-admin.card>
    @endif

    @if ($homeId !== 0)
    <x-admin.card title="The slides" description="Top to bottom is the rotation order. The first slide shows while the rest load — keep the strongest photograph there.">
        @if ($fromHero)
            <div class="mb-4 rounded-xl border border-brand-200 bg-brand-50/60 px-4 py-3 text-theme-sm text-gray-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-gray-200" role="status">
                <p class="font-medium">The home page opens with a single hero — shown here as its one slide.</p>
                <p class="mt-1 text-theme-xs text-gray-600 dark:text-gray-300">
                    Save writes it back to the page's hero, key figures untouched.
                    Add a second slide to turn it into the rotating slider: the old hero section then stays on the page, hidden, so nothing it held is lost.
                </p>
            </div>
        @endif

        @if (count($slides) === 0)
            <x-admin.empty icon="image" title="No hero slider yet" description="Add the first slide and press Save — the hero slider is created on the home page as its opening section." />
        @endif

        <div class="space-y-4">
            @foreach ($slides as $i => $slide)
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <p class="text-theme-sm font-medium text-gray-900 dark:text-white">Slide {{ $i + 1 }}</p>
                        <div class="flex items-center gap-1">
                            <x-admin.button variant="secondary" wire:click="moveSlide({{ $i }}, -1)" :disabled="$i === 0">Up</x-admin.button>
                            <x-admin.button variant="secondary" wire:click="moveSlide({{ $i }}, 1)" :disabled="$i === count($slides) - 1">Down</x-admin.button>
                            <x-admin.button variant="danger" icon="trash" wire:click="removeSlide({{ $i }})" wire:confirm="Remove slide {{ $i + 1 }}?">Remove</x-admin.button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <x-admin.field label="Photograph" hint="Picked from Website → Media. Alt text is set there.">
                            <select wire:model.live="slides.{{ $i }}.image_id" class="wx-input">
                                <option value="">No image</option>
                                @foreach ($media as $image)
                                    <option value="{{ $image->id }}">{{ $image->title ?: basename($image->path) }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>
                        <x-admin.field label="Small label (kicker)">
                            <input type="text" wire:model.blur="slides.{{ $i }}.kicker" class="wx-input" placeholder="Lahore · since 2016">
                        </x-admin.field>
                        <x-admin.field label="Heading">
                            <textarea rows="2" wire:model.blur="slides.{{ $i }}.heading" class="wx-input" placeholder="Spaces shaped by purpose"></textarea>
                        </x-admin.field>
                        <x-admin.field label="Text">
                            <textarea rows="2" wire:model.blur="slides.{{ $i }}.text" class="wx-input" placeholder="One sentence that says what the studio promises."></textarea>
                        </x-admin.field>
                        <x-admin.field label="Big display word (optional)" hint="The oversized word behind the text, like the live site's 'Design'.">
                            <input type="text" wire:model.blur="slides.{{ $i }}.display" class="wx-input" placeholder="Design">
                        </x-admin.field>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                            <p class="mb-2 text-theme-xs font-medium uppercase tracking-wider text-gray-400">Buttons</p>
                            <div class="grid grid-cols-2 gap-3">
                                <x-admin.field label="Button label">
                                    <input type="text" wire:model.blur="slides.{{ $i }}.primary_label" class="wx-input" placeholder="Start your project">
                                </x-admin.field>
                                <x-admin.field label="Button link">
                                    <input type="text" wire:model.blur="slides.{{ $i }}.primary_url" class="wx-input" placeholder="/contact/">
                                </x-admin.field>
                                <x-admin.field label="Second button label">
                                    <input type="text" wire:model.blur="slides.{{ $i }}.secondary_label" class="wx-input">
                                </x-admin.field>
                                <x-admin.field label="Second button link">
                                    <input type="text" wire:model.blur="slides.{{ $i }}.secondary_url" class="wx-input">
                                </x-admin.field>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <x-admin.button variant="secondary" icon="plus" wire:click="addSlide">Add a slide</x-admin.button>
            <div class="ms-auto flex items-center gap-2">
                <x-admin.button variant="secondary" wire:click="save(false)" :disabled="count($slides) === 0">{{ $fromHero ? "Save hero" : "Save draft" }}</x-admin.button>
                @can('website.publish')
                    <x-admin.button icon="check" wire:click="save(true)" :disabled="count($slides) === 0">{{ $fromHero ? "Publish the hero" : "Publish to the home page" }}</x-admin.button>
                @endcan
            </div>
        </div>
    </x-admin.card>
    @endif
</div>
