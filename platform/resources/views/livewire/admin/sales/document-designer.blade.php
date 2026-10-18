<div>
    <x-admin.page-header title="Document design" description="Shared quotation and invoice identity for each brand. Saved styles are copied into new documents—not pushed into historical PDFs." :breadcrumbs="['Sales' => null, 'Document design' => null]" />
    <div class="mb-5 max-w-sm"><x-admin.field label="Brand" for="line"><select id="line" wire:model.live="line" class="wx-input"><option value="interior">Woodex Interior / Project</option><option value="furniture">Woodex Furniture</option></select></x-admin.field></div>
    <div class="grid gap-5 lg:grid-cols-2">
        <x-admin.card>
            <form wire:submit="save" class="space-y-4">
                <x-admin.field label="Brand name" for="form.brand_name"><input id="form.brand_name" wire:model="form.brand_name" class="wx-input" maxlength="35"></x-admin.field>
                <x-admin.field label="Accent color" for="form.accent_color" hint="Use a dark color with good contrast against white text."><input id="form.accent_color" type="color" wire:model="form.accent_color" class="h-11 w-24 rounded-lg border border-gray-200"></x-admin.field>
                <x-admin.field label="Header / tagline" for="form.header_text" hint="Empty uses the business tagline from Settings."><input id="form.header_text" wire:model="form.header_text" class="wx-input" maxlength="100"></x-admin.field>
                <x-admin.field label="Footer text" for="form.footer_text" hint="Empty keeps the standard legal/document footer."><input id="form.footer_text" wire:model="form.footer_text" class="wx-input" maxlength="120"></x-admin.field>
                <x-admin.field label="Logo" for="logo" hint="JPEG or PNG, maximum 96 KB and 600 × 240 pixels. Stored privately in the design and copied into document snapshots."><input id="logo" type="file" wire:model="logo" accept="image/png,image/jpeg" class="wx-input"></x-admin.field>
                <label class="flex items-center gap-2 text-theme-sm text-gray-600"><input type="checkbox" wire:model="removeLogo" class="wx-checkbox">Remove saved logo</label>
                <x-admin.button type="submit" wire:loading.attr="disabled" wire:target="save,logo">Save design</x-admin.button>
            </form>
        </x-admin.card>
        <x-admin.card>
            <h2 class="mb-4 font-medium text-gray-900 dark:text-white">Saved identity · revision {{ $design->revision }}</h2>
            @if ($design->logo_data_uri)<img src="{{ $design->logo_data_uri }}" alt="Saved brand logo" class="mb-3 max-h-16 max-w-full object-contain">@endif
            <p class="text-xl font-semibold text-gray-900 dark:text-white">{{ $design->brand_name }}</p>
            <p class="mt-2 text-theme-sm text-gray-600 dark:text-gray-300">{{ $design->header_text ?: 'Business tagline from Settings' }}</p>
            <div class="my-5 border-t border-gray-200 pt-5 text-theme-sm text-gray-500">{{ $design->footer_text ?: 'Standard legal/document footer' }}</div>
            <p class="text-theme-sm text-gray-500">The card shows the saved identity, not a print proof. Create a new quote—or explicitly apply the current design to an editable draft—then use <strong>Preview PDF</strong> to check the real layout in this window.</p>
            <p class="mt-3 text-theme-xs text-gray-500">Business contact/address and payment details remain in their existing Settings screens. Photo columns, paper presets and advanced layouts are planned separately.</p>
        </x-admin.card>
    </div>
</div>
