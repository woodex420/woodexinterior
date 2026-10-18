<div>
    <x-admin.page-header :title="$estimateId !== null ? 'Estimate '.$number : 'New estimate'" description="Same engine as the website estimator: pick the service, give the size and finish, and the range is honest — essential to premium. Add known rate book items and an explicit contingency. Converting creates a quotation draft; nothing reaches a client from here."
                         :breadcrumbs="['Sales' => null, 'Estimations' => route('admin.estimations.index'), $estimateId !== null ? $number : 'New' => null]">
        <x-slot:actions>
            @if ($estimateId !== null && $estimate?->quote !== null)
                <a href="{{ route('admin.quotes.show', $estimate->quote) }}"><x-admin.button variant="secondary" icon="quote">Quotation {{ $estimate->quote->number }}</x-admin.button></a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-admin.card title="The job">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="wx-label">Service</label>
                        <select wire:model.live="serviceKey" class="wx-input">
                            <option value="">— choose the service —</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->key }}">{{ $service->label }} ({{ $service->unit_label ?? $service->unit->value }})</option>
                            @endforeach
                        </select>
                        @error('serviceKey')<p class="mt-1 text-theme-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="wx-label">Title</label>
                        <input wire:model="title" class="wx-input" placeholder="e.g. 10 marla lounge — DHA Phase 5" maxlength="120" />
                        @error('title')<p class="mt-1 text-theme-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="wx-label">Client</label>
                        <select wire:model="contactId" class="wx-input">
                            <option value="">— choose the client (needed to convert) —</option>
                            @foreach ($contacts as $contact)
                                <option value="{{ $contact->id }}">{{ $contact->name }}</option>
                            @endforeach
                        </select>
                        @error('contactId')<p class="mt-1 text-theme-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="wx-label">Size / quantity</label>
                        <input wire:model.live="quantity" class="wx-input" placeholder="e.g. 2400" inputmode="decimal" />
                        @error('quantity')<p class="mt-1 text-theme-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="wx-label">Finish level</label>
                        <div class="flex flex-wrap gap-3">
                            @foreach (['essential' => 'Essential', 'standard' => 'Standard', 'premium' => 'Premium'] as $value => $label)
                                <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-theme-sm {{ $finish === $value ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/30' : 'border-gray-200 dark:border-gray-800' }}">
                                    <input type="radio" wire:model.live="finish" value="{{ $value }}" />
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="wx-label">Contingency (basis points)</label>
                        <input wire:model.live="contingencyBp" class="wx-input" inputmode="numeric" placeholder="0 – 10000 (e.g. 500 = 5%)" />
                        @error('contingencyBp')<p class="mt-1 text-theme-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="Rate book items (optional)">
                <p class="mb-3 text-theme-sm text-gray-500 dark:text-gray-400">Known items priced from the rate book at today's rates — they become their own section on the quotation.</p>
                <div class="space-y-3">
                    @foreach ($catalogLines as $index => $line)
                        <div class="grid grid-cols-12 gap-2 items-end" wire:key="line-{{ $index }}">
                            <div class="col-span-7">
                                <label class="wx-label">Item</label>
                                <select wire:model="catalogLines.{{ $index }}.catalog_item_id" class="wx-input">
                                    <option value="">— item —</option>
                                    @foreach ($catalogItems as $item)
                                        <option value="{{ $item->id }}">{{ $item->category }} — {{ Str::limit($item->description, 60) }} · {{ \App\Support\Money::ofMinor($item->rate_minor)->format() }}/{{ $item->unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-3">
                                <label class="wx-label">Qty</label>
                                <input wire:model="catalogLines.{{ $index }}.qty" class="wx-input" inputmode="decimal" />
                            </div>
                            <div class="col-span-2">
                                <x-admin.button variant="secondary" type="button" wire:click="removeLine({{ $index }})">Remove</x-admin.button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3">
                    <x-admin.button variant="secondary" icon="plus" type="button" wire:click="addLine">Add item</x-admin.button>
                </div>
                @error('catalogLines')<p class="mt-2 text-theme-sm text-red-600">{{ $message }}</p>@enderror
            </x-admin.card>

            <x-admin.card title="Assumptions & exclusions">
                <div class="grid gap-4">
                    <div>
                        <label class="wx-label">Assumptions (internal + carried to the quote's notes)</label>
                        <textarea wire:model="assumptions" class="wx-input" rows="3" maxlength="4000" placeholder="e.g. rates per sq ft of covered area; site access during working hours; 3D views excluded"></textarea>
                    </div>
                    <div>
                        <label class="wx-label">Exclusions</label>
                        <textarea wire:model="exclusions" class="wx-input" rows="3" maxlength="4000" placeholder="e.g. civil structure work, MEP by others, approvals"></textarea>
                    </div>
                </div>
            </x-admin.card>
        </div>

        <div class="space-y-6">
            <x-admin.card title="Priced now">
                @if ($summary === null)
                    <p class="text-theme-sm text-gray-500 dark:text-gray-400">Choose a service and give the size to see the range.</p>
                @else
                    <dl class="space-y-2 text-theme-sm">
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Rate ({{ $finish }})</dt><dd class="font-medium">{{ $summary['rate_minor'] }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Headline</dt><dd class="font-medium">{{ $summary['headline'] }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Honest range</dt><dd class="font-medium">{{ $summary['range_low'] }} – {{ $summary['range_high'] }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Rate book items</dt><dd class="font-medium">{{ $summary['lines_minor'] }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Contingency</dt><dd class="font-medium">{{ $summary['contingency'] }}</dd></div>
                        <div class="flex justify-between border-t border-gray-100 dark:border-gray-800 pt-2"><dt class="font-medium">Total</dt><dd class="font-semibold">{{ $summary['total'] }}</dd></div>
                    </dl>
                    <p class="mt-3 text-xs text-gray-400">Rates as of {{ $summary['effective_on'] }} — the snapshot freezes them when you save.</p>
                @endif
            </x-admin.card>

            @can('estimates.manage')
                <x-admin.card title="Actions">
                    <div class="space-y-3">
                        <x-admin.button wire:click="save" class="w-full justify-center">{{ $estimateId !== null ? 'Save changes' : 'Save estimate' }}</x-admin.button>
                        @if ($estimateId !== null && $status !== 'converted')
                            <x-admin.button variant="secondary" wire:click="review" class="w-full justify-center">Mark reviewed</x-admin.button>
                            <x-admin.button variant="secondary" wire:click="convert" class="w-full justify-center" wire:confirm="Create a quotation draft from this estimate?">Convert to quotation</x-admin.button>
                        @endif
                        @if ($status === 'converted')
                            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Converted — the quotation carries it from here.</p>
                        @endif
                    </div>
                </x-admin.card>
            @endcan
        </div>
    </div>
</div>
