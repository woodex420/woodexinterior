<div>
    <x-admin.page-header :title="$title" :description="$description" :breadcrumbs="$breadcrumbs">
    </x-admin.page-header>

    <div class="mb-6 overflow-x-auto" role="tablist" aria-label="{{ $title }} views">
        <div class="flex w-max min-w-full items-center gap-1 rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-gray-800 dark:bg-gray-900/60">
            @foreach ($tabs as $tab)
                @continue(! $tab['visible'])
                <a href="?tab={{ $tab['id'] }}" wire:navigate role="tab"
                   aria-selected="{{ $tab['id'] === $active['id'] ? 'true' : 'false' }}"
                   @class([
                       'rounded-lg px-4 py-2 text-theme-sm font-medium whitespace-nowrap transition',
                       'bg-white text-navy-900 shadow-sm dark:bg-gray-800 dark:text-white' => $tab['id'] === $active['id'],
                       'text-gray-500 hover:text-navy-900 dark:text-gray-400 dark:hover:text-white' => $tab['id'] !== $active['id'],
                   ])>
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    <livewire:dynamic-component :component="$activeAlias" :key="'hub-'.$active['id']" />
</div>
