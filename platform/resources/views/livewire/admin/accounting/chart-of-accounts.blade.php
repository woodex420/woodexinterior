@use('App\Domain\Accounting\Enums\AccountType')
<div>
    <x-admin.page-header title="Chart of accounts"
                         description="The accounts every invoice, payment and expense posts to. System accounts keep the codes the posting rules use: rename them freely, never delete them."
                         :breadcrumbs="['Accounting' => null, 'Chart of accounts' => null]">
        <x-slot:actions>
            @can('accounting.manage')
                <x-admin.button icon="plus" wire:click="openCreate">New account</x-admin.button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    @foreach ($groups as $group)
        <x-admin.card class="mb-4" :padding="false">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                <h2 class="text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ $group['type']->title() }}</h2>
                <span class="tabular-nums text-theme-sm {{ $group['total'] === 0 ? 'text-gray-400' : 'text-gray-700 dark:text-gray-200' }}">
                    {{ $format($group['total']) }}
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-theme-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($group['accounts'] as $account)
                            <tr wire:key="account-{{ $account->id }}" @class(['hover:bg-gray-50 dark:hover:bg-white/[0.02]', 'opacity-50' => ! $account->is_active])>
                                <td class="w-24 px-5 py-3 font-mono text-theme-xs text-gray-500">{{ $account->code }}</td>
                                <td class="px-3 py-3">
                                    <span class="text-gray-800 dark:text-white/90">{{ $account->name }}</span>
                                    <span class="ml-2 text-theme-xs text-gray-400">{{ $account->subtype->label() }}</span>
                                    @if ($account->is_system)
                                        <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500 dark:bg-gray-800">system</span>
                                    @endif
                                    @unless ($account->is_active)
                                        <span class="ml-1 text-theme-xs text-warning-700">hidden</span>
                                    @endunless
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums {{ ($signed[$account->id] ?? 0) === 0 ? 'text-gray-400' : 'text-gray-800 dark:text-white/90' }}">
                                    {{ $format((int) ($signed[$account->id] ?? 0)) }}
                                </td>
                                @can('accounting.manage')
                                    <td class="px-5 py-3 text-right whitespace-nowrap">
                                        <x-admin.button size="sm" variant="ghost" icon="pencil" wire:click="openEdit({{ $account->id }})" aria-label="Edit {{ $account->name }}" />
                                        <x-admin.button size="sm" variant="ghost" wire:click="toggleActive({{ $account->id }})">{{ $account->is_active ? 'Hide' : 'Show' }}</x-admin.button>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-admin.empty icon="book" title="No accounts in this group" description="Add an account to start posting." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    @endforeach

    @if ($showForm)
        <x-admin.modal name="showForm" :title="$editingId ? 'Edit account' : 'New account'" submit="save">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-admin.field label="Code" for="formCode" hint="1100, 4000, 6100 …">
                    <input id="formCode" wire:model="formCode" class="wx-input" autocomplete="off">
                </x-admin.field>
                <x-admin.field label="Name" for="formName">
                    <input id="formName" wire:model="formName" class="wx-input">
                </x-admin.field>
                <x-admin.field label="Type" for="formType">
                    <select id="formType" wire:model="formType" class="wx-input">
                        @foreach (\App\Domain\Accounting\Enums\AccountType::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <x-admin.field label="Subtype" for="formSubtype">
                    <select id="formSubtype" wire:model="formSubtype" class="wx-input">
                        @foreach (\App\Domain\Accounting\Enums\AccountSubtype::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <x-admin.field label="Opening balance (Rs)" for="formOpening" hint="Debit-positive for assets and expenses. Posted with the opening journal, not here.">
                    <input id="formOpening" wire:model="formOpening" class="wx-input" inputmode="decimal" placeholder="0">
                </x-admin.field>
                <x-admin.field label="Available">
                    <label class="flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model="formActive" class="size-4 rounded border-gray-300"> Can be used in new entries
                    </label>
                </x-admin.field>
            </div>

            <x-slot:footer>
                <x-admin.button variant="secondary" wire:click="$set('showForm', false)">Cancel</x-admin.button>
                <x-admin.button type="submit">Save account</x-admin.button>
            </x-slot:footer>
        </x-admin.modal>
    @endif
</div>
