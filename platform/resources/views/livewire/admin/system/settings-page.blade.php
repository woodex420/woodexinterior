<div>
    <x-admin.page-header title="Settings" description="Business details appear on quotations, invoices and the website. Sales rules drive SLAs, discounts and reports."
                         :breadcrumbs="['System' => null, 'Settings' => null]" />

    <div class="mb-6 inline-flex rounded-lg border border-gray-200 bg-white p-1 dark:border-gray-800 dark:bg-white/[0.03]" role="tablist">
        @foreach (['business' => 'Business', 'sales' => 'Sales rules', 'leads' => 'Lead rules', 'notifications' => 'Notifications'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    @class(['rounded-md px-4 py-1.5 text-theme-sm font-medium transition', 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' => $tab === $key, 'text-gray-600 hover:text-gray-900 dark:text-gray-400' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    {{-- Business --}}
    @if ($tab === 'business')
        <form wire:submit="saveBusiness" class="space-y-6">
            <x-admin.card title="Identity" description="Shown on documents and the website footer.">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-admin.field label="Legal name" for="business.legal_name"><input id="business.legal_name" type="text" wire:model="business.legal_name" class="wx-input"></x-admin.field>
                    <x-admin.field label="Brand name" for="business.brand_name"><input id="business.brand_name" type="text" wire:model="business.brand_name" class="wx-input"></x-admin.field>
                    <x-admin.field label="Furniture brand" for="business.furniture_brand_name"><input id="business.furniture_brand_name" type="text" wire:model="business.furniture_brand_name" class="wx-input"></x-admin.field>
                    <x-admin.field label="Founded" for="business.founded_year"><input id="business.founded_year" type="number" wire:model="business.founded_year" class="wx-input"></x-admin.field>
                    <x-admin.field label="Tagline" for="business.tagline" class="md:col-span-2"><input id="business.tagline" type="text" wire:model="business.tagline" class="wx-input"></x-admin.field>
                    <x-admin.field label="NTN (optional)" for="business.ntn"><input id="business.ntn" type="text" wire:model="business.ntn" class="wx-input"></x-admin.field>
                    <x-admin.field label="Website" for="business.website"><input id="business.website" type="url" wire:model="business.website" class="wx-input"></x-admin.field>
                </div>
            </x-admin.card>

            <x-admin.card title="Contact" description="The WhatsApp number receives website chats and is printed on quotations.">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-admin.field label="Phone" for="business.phone"><input id="business.phone" type="tel" wire:model="business.phone" class="wx-input"></x-admin.field>
                    <x-admin.field label="WhatsApp" for="business.whatsapp"><input id="business.whatsapp" type="tel" wire:model="business.whatsapp" class="wx-input"></x-admin.field>
                    <x-admin.field label="Email" for="business.email"><input id="business.email" type="email" wire:model="business.email" class="wx-input"></x-admin.field>
                    <x-admin.field label="City" for="business.city"><input id="business.city" type="text" wire:model="business.city" class="wx-input"></x-admin.field>
                    <x-admin.field label="Studio address" for="business.address" class="md:col-span-2"><input id="business.address" type="text" wire:model="business.address" class="wx-input"></x-admin.field>
                </div>
            </x-admin.card>

            <x-admin.card title="Working hours" description="Response-time targets only count working hours, so a lead at 11 pm is due the next morning.">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <x-admin.field label="Opens" for="business.opens_at"><input id="business.opens_at" type="time" wire:model="business.opens_at" class="wx-input"></x-admin.field>
                    <x-admin.field label="Closes" for="business.closes_at"><input id="business.closes_at" type="time" wire:model="business.closes_at" class="wx-input"></x-admin.field>
                    <x-admin.field label="Working days" error="business.working_days" class="md:col-span-3">
                        <div class="flex flex-wrap gap-2">
                            @foreach ($days as $number => $label)
                                <label class="cursor-pointer">
                                    <input type="checkbox" wire:model="business.working_days" value="{{ $number }}" class="peer sr-only">
                                    <span class="inline-flex min-w-14 justify-center rounded-lg border border-gray-200 px-3 py-2 text-theme-sm text-gray-600 peer-checked:border-navy-900 peer-checked:bg-navy-900 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-navy-300 dark:border-gray-700 dark:text-gray-300 dark:peer-checked:bg-white dark:peer-checked:text-navy-900">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </x-admin.field>
                </div>
            </x-admin.card>

            <x-admin.card title="Holidays" description="Closed days pause response timers and reminders (AUT-004). Add Eid and other moon-dependent holidays when they are announced.">
                <x-admin.field label="One per line: date and name" for="business.holidays" hint="Example: 2027-03-10 Eid ul Fitr (day 1)">
                    <textarea id="business.holidays" wire:model="business.holidays" rows="6" class="wx-input font-mono text-theme-xs"></textarea>
                </x-admin.field>
            </x-admin.card>

            <x-admin.card title="Social profiles" description="Used in the website footer and structured data.">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach (\App\Livewire\Admin\System\SettingsPage::SOCIAL_NETWORKS as $key => $label)
                        <x-admin.field :label="$label" for="business.social_links.{{ $key }}">
                            <input id="business.social_links.{{ $key }}" type="url" wire:model="business.social_links.{{ $key }}" class="wx-input" placeholder="https://">
                        </x-admin.field>
                    @endforeach
                </div>
            </x-admin.card>

            <div class="flex justify-end"><x-admin.button type="submit" icon="check">Save business settings</x-admin.button></div>
        </form>
    @endif

    {{-- Sales --}}
    @if ($tab === 'sales')
        <form wire:submit="saveSales" class="space-y-6">
            <x-admin.card title="Quotations and invoices">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <x-admin.field label="Quotation valid for (days)" for="sales.quote_validity_days"><input id="sales.quote_validity_days" type="number" min="1" wire:model="sales.quote_validity_days" class="wx-input"></x-admin.field>
                    <x-admin.field label="Invoice due after (days)" for="sales.invoice_due_days"><input id="sales.invoice_due_days" type="number" min="0" wire:model="sales.invoice_due_days" class="wx-input"></x-admin.field>
                    <x-admin.field label="Minimum margin % (optional)" for="sales.min_margin" hint="Quotations below this need approval."><input id="sales.min_margin" type="number" step="0.5" min="0" max="100" wire:model="sales.min_margin" class="wx-input"></x-admin.field>
                    <label class="flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300"><input type="checkbox" wire:model="sales.round_totals_to_rupee" class="rounded border-gray-300"> Round totals to whole rupees</label>
                    <label class="flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300 md:col-span-2"><input type="checkbox" wire:model="sales.lakh_grouping" class="rounded border-gray-300"> Show amounts in lakh format (12,50,000 instead of 1,250,000)</label>
                    <label class="flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300 md:col-span-3"><input type="checkbox" wire:model="sales.quote_follow_ups_enabled" class="rounded border-gray-300"> Chase quotations nobody answered (the team is told to call; the system never writes to a client by itself)</label>
                    <x-admin.field label="Chase after (quiet days)" for="sales.quote_follow_up_days" hint="How long a quotation may sit unanswered before the team is told to call. Three chases, then it is left alone."><input id="sales.quote_follow_up_days" type="number" min="1" max="60" wire:model="sales.quote_follow_up_days" class="wx-input"></x-admin.field>
                    <x-admin.field label="Warn before it lapses (days)" for="sales.quote_expiry_warning_days" hint="Told once per quotation, while the price can still be saved."><input id="sales.quote_expiry_warning_days" type="number" min="1" max="60" wire:model="sales.quote_expiry_warning_days" class="wx-input"></x-admin.field>
                </div>
            </x-admin.card>

            <x-admin.card title="Discount limits" description="Above these, a quotation needs approval from someone with a higher limit.">
                <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                    @foreach (\App\Livewire\Admin\System\SettingsPage::DISCOUNT_ROLES as $role => $label)
                        <x-admin.field :label="$label" for="sales.discount_limits.{{ $role }}">
                            <div class="relative">
                                <input id="sales.discount_limits.{{ $role }}" type="number" step="0.5" min="0" max="100" wire:model="sales.discount_limits.{{ $role }}" class="wx-input pr-8">
                                <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-theme-sm text-gray-400">%</span>
                            </div>
                        </x-admin.field>
                    @endforeach
                </div>
            </x-admin.card>

            <x-admin.card title="Leads">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="space-y-4">
                        <x-admin.field label="First response target (working minutes)" for="sales.sla_first_response_minutes" hint="New leads must be called or messaged within this time, or managers are alerted.">
                            <input id="sales.sla_first_response_minutes" type="number" min="5" wire:model="sales.sla_first_response_minutes" class="wx-input">
                        </x-admin.field>
                        <x-admin.field label="Lost reasons (one per line)" for="sales.lost_reasons">
                            <textarea id="sales.lost_reasons" wire:model="sales.lost_reasons" rows="7" class="wx-input h-auto py-2.5"></textarea>
                        </x-admin.field>
                    </div>
                    <div>
                        <span class="wx-label">Budget bands</span>
                        <p class="mb-2 text-theme-xs text-gray-500">Keys are stored on leads, so keep them stable; labels can change any time.</p>
                        <div class="space-y-2">
                            @foreach ($sales['budget_bands'] as $i => $band)
                                <div class="grid grid-cols-[7rem_1fr_2rem] gap-2" wire:key="band-{{ $i }}">
                                    <input type="text" wire:model="sales.budget_bands.{{ $i }}.key" class="wx-input font-mono text-theme-xs" placeholder="3m-7m" aria-label="Key">
                                    <input type="text" wire:model="sales.budget_bands.{{ $i }}.label" class="wx-input" placeholder="Rs 30–70 lakh" aria-label="Label">
                                    <button type="button" wire:click="removeBand({{ $i }})" class="text-gray-400 hover:text-error-600" aria-label="Remove band"><x-icon name="x" class="size-4" /></button>
                                </div>
                            @endforeach
                        </div>
                        @error('sales.budget_bands.*.key') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                        @error('sales.budget_bands.*.label') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                        <button type="button" wire:click="addBand" class="mt-2 inline-flex items-center gap-1 text-theme-sm font-medium text-navy-700 hover:underline dark:text-gray-300"><x-icon name="plus" class="size-4" /> Add band</button>
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="Terms and signatures" description="Printed at the end of every quotation.">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-admin.field label="Execution terms" for="sales.execution_terms"><textarea id="sales.execution_terms" wire:model="sales.execution_terms" rows="6" class="wx-input h-auto py-2.5"></textarea></x-admin.field>
                    <x-admin.field label="Design terms" for="sales.design_terms"><textarea id="sales.design_terms" wire:model="sales.design_terms" rows="6" class="wx-input h-auto py-2.5"></textarea></x-admin.field>
                    <div class="md:col-span-2">
                        <span class="wx-label">Signatories</span>
                        <div class="space-y-2">
                            @foreach ($sales['signatories'] as $i => $signatory)
                                <div class="grid grid-cols-[1fr_1fr_2rem] gap-2" wire:key="sig-{{ $i }}">
                                    <input type="text" wire:model="sales.signatories.{{ $i }}.name" class="wx-input" placeholder="Name" aria-label="Name">
                                    <input type="text" wire:model="sales.signatories.{{ $i }}.title" class="wx-input" placeholder="Title" aria-label="Title">
                                    <button type="button" wire:click="removeSignatory({{ $i }})" class="text-gray-400 hover:text-error-600" aria-label="Remove signatory"><x-icon name="x" class="size-4" /></button>
                                </div>
                            @endforeach
                        </div>
                        @error('sales.signatories.*.name') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                        @if (count($sales['signatories']) < 4)
                            <button type="button" wire:click="addSignatory" class="mt-2 inline-flex items-center gap-1 text-theme-sm font-medium text-navy-700 hover:underline dark:text-gray-300"><x-icon name="plus" class="size-4" /> Add signatory</button>
                        @endif
                    </div>
                </div>
            </x-admin.card>

            <div class="flex justify-end"><x-admin.button type="submit" icon="check">Save sales rules</x-admin.button></div>
        </form>
    @endif

    {{-- Lead rules (own component) --}}
    @if ($tab === 'leads')
        <livewire:admin.system.lead-rules-settings />
    @endif

    {{-- Notifications --}}
    @if ($tab === 'notifications')
        <form wire:submit="saveNotifications" class="space-y-6">
            <x-admin.card title="Telegram alerts" description="Each person links their own Telegram under Security & Telegram.">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <label class="flex items-center gap-2 text-theme-sm text-gray-700 md:col-span-3 dark:text-gray-300"><input type="checkbox" wire:model="notifications.telegram_enabled" class="rounded border-gray-300"> Send alerts to Telegram</label>
                    <x-admin.field label="Quiet hours start" for="notifications.quiet_hours_start" hint="Non-urgent alerts wait until quiet hours end."><input id="notifications.quiet_hours_start" type="time" wire:model="notifications.quiet_hours_start" class="wx-input"></x-admin.field>
                    <x-admin.field label="Quiet hours end" for="notifications.quiet_hours_end"><input id="notifications.quiet_hours_end" type="time" wire:model="notifications.quiet_hours_end" class="wx-input"></x-admin.field>
                    <x-admin.field label="Daily summary at" for="notifications.daily_digest_time"><input id="notifications.daily_digest_time" type="time" wire:model="notifications.daily_digest_time" class="wx-input"></x-admin.field>
                </div>
            </x-admin.card>
            <x-admin.card title="Escalation" description="Who hears about unassigned leads and missed response times.">
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach ($roles as $key => $description)
                        <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2.5 text-theme-sm hover:bg-gray-50 has-[:checked]:border-navy-900 has-[:checked]:bg-navy-50 dark:border-gray-700 dark:hover:bg-white/5 dark:has-[:checked]:bg-white/10">
                            <input type="checkbox" wire:model="notifications.unassigned_lead_roles" value="{{ $key }}" class="rounded border-gray-300">
                            <span class="font-medium text-gray-800 dark:text-white/90">{{ \Illuminate\Support\Str::headline($key) }}</span>
                        </label>
                    @endforeach
                </div>
                @error('notifications.unassigned_lead_roles') <p class="mt-2 text-theme-xs text-error-600">{{ $message }}</p> @enderror
            </x-admin.card>
            <x-admin.card title="Final escalation" description="Alerted when a missed response is still open after the escalation delay (Lead rules tab).">
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach ($roles as $key => $description)
                        <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2.5 text-theme-sm hover:bg-gray-50 has-[:checked]:border-navy-900 has-[:checked]:bg-navy-50 dark:border-gray-700 dark:hover:bg-white/5 dark:has-[:checked]:bg-white/10">
                            <input type="checkbox" wire:model="notifications.sla_final_roles" value="{{ $key }}" class="rounded border-gray-300">
                            <span class="font-medium text-gray-800 dark:text-white/90">{{ \Illuminate\Support\Str::headline($key) }}</span>
                        </label>
                    @endforeach
                </div>
                @error('notifications.sla_final_roles') <p class="mt-2 text-theme-xs text-error-600">{{ $message }}</p> @enderror
            </x-admin.card>
            <div class="flex justify-end"><x-admin.button type="submit" icon="check">Save notification settings</x-admin.button></div>
        </form>
    @endif
</div>
