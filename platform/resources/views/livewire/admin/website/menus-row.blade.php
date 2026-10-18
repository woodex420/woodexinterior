@php
    $siblings = $childrenOf($item->parent_id);
    $first = $siblings->first()?->id === $item->id;
    $last = $siblings->last()?->id === $item->id;
    $children = $childrenOf($item->id);
@endphp
<tr wire:key="nav-{{ $item->id }}" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
    <td class="px-5 py-3 font-medium text-gray-800 dark:text-white/90">
        <span class="flex items-center gap-2" @style(['padding-left: '.($level * 1.75).'rem'])>
            @if ($level > 0)<span class="text-gray-300" aria-hidden="true">└</span>@endif
            {{ $item->label }}
            @if ($level === 0 && $children->isNotEmpty())
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-theme-xs text-gray-500 dark:bg-white/10">{{ $children->count() }} under it</span>
            @endif
        </span>
    </td>
    <td class="px-5 py-3 font-mono text-theme-xs text-gray-600 dark:text-gray-400">{{ $item->url }}</td>
    <td class="hidden px-5 py-3 text-theme-xs md:table-cell">
        @if ($level === 0 && $children->isNotEmpty())
            <button type="button" wire:click="toggleMega({{ $item->id }})" wire:key="mega-{{ $item->id }}"
                    @class(['wx-mini-btn' => true, 'border-brand-500 text-brand-700 dark:text-brand-300' => $item->is_mega])>
                {{ $item->is_mega ? 'Mega panel' : 'Dropdown' }}
            </button>
        @elseif ($level === 0)
            <span class="text-theme-xs text-gray-400">Plain link</span>
        @else
            <span class="text-theme-xs text-gray-400">{{ $item->target === '_blank' ? 'New tab' : 'Same tab' }}</span>
        @endif
    </td>
    <td class="px-5 py-3">
        <div class="flex items-center justify-end gap-2">
            <button type="button" wire:click="move({{ $item->id }}, -1)" class="wx-mini-btn" aria-label="Move up" @if ($first) disabled @endif>↑</button>
            <button type="button" wire:click="move({{ $item->id }}, 1)" class="wx-mini-btn" aria-label="Move down" @if ($last) disabled @endif>↓</button>
            <button type="button" wire:click="$set('parent', '{{ $item->id }}'); $wire.$dispatch('focus-label')" class="wx-mini-btn" title="Add a link under this one" aria-label="Add under {{ $item->label }}">＋</button>
            <button type="button" wire:click="remove({{ $item->id }})" wire:confirm="Remove “{{ $item->label }}” and everything under it?" class="text-theme-sm text-error-600 hover:underline">Remove</button>
        </div>
    </td>
</tr>
@foreach ($children as $child)
    @include('livewire.admin.website.menus-row', ['item' => $child, 'level' => $level + 1, 'childrenOf' => $childrenOf])
@endforeach
