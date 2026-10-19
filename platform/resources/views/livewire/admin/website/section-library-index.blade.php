<div>
    <x-admin.page-header title="Section library" description="Every section saved from a page builder — a gallery that took an hour, a price table tuned to the paisa. Rename or retire them here; the page builder's shelf drops them onto any page, content and all."
                         :breadcrumbs="['Website' => null, 'Section library' => null]">
        <x-slot:actions>
            <x-admin.button variant="secondary" icon="layout" href="{{ route('admin.website.pages') }}">All pages</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3">
        <x-admin.stat label="Saved sections" :value="$sections->count()" icon="layers" />
        <x-admin.stat label="Section types" :value="$sections->pluck('type')->unique()->count()" icon="layout" tone="wood" />
        <x-admin.stat label="Newest" :value="$sections->first()?->created_at?->format('d M Y') ?? '—'" icon="clock" />
    </div>

    <x-admin.card title="The shelf" description="Saved from the page builder's section menu — 'Save to library'. Nothing here changes a page until a builder drops it on.">
        @if ($sections->isEmpty())
            <x-admin.empty icon="layers" title="Nothing saved yet" description="In the page builder, open a section's menu and press Save to library — it appears here, ready for any page." />
        @endif

        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($sections as $section)
                <div class="flex flex-wrap items-center gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-theme-sm font-medium text-gray-900 dark:text-white">{{ $section->name }}</p>
                        <p class="mt-0.5 text-theme-xs text-gray-500">
                            {{ $section->type->label() }} · {{ $section->type->description() }}
                        </p>
                        <p class="mt-0.5 text-theme-xs text-gray-400">
                            @if ($section->creator) Saved by {{ $section->creator->name }} · @endif {{ $section->created_at?->format('d M Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-admin.button variant="secondary" icon="pencil" wire:click="rename({{ $section->id }})">Rename</x-admin.button>
                        <x-admin.button variant="danger" icon="trash" wire:click="delete({{ $section->id }})" wire:confirm="Remove '{{ $section->name }}' from the library? Pages already using it keep their copy.">Delete</x-admin.button>
                    </div>
                </div>
            @endforeach
        </div>
    </x-admin.card>

    @if ($showRename)
        <x-admin.modal name="showRename" title="Rename the section" submit="saveRename">
            <x-admin.field label="Name" for="renameName">
                <input id="renameName" type="text" wire:model="renameName" class="wx-input">
            </x-admin.field>
        </x-admin.modal>
    @endif
</div>
