<div>
    <x-admin.page-header title="Website settings" description="The header button, the social profiles, the footer's words and switches, and the website's analytics. Nothing analytics is loaded until an ID is filled in here, and nothing is loaded for signed-in staff."
                         :breadcrumbs="['Website' => null, 'Settings' => null]" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <form wire:submit="save" class="contents">
            <x-admin.card title="Header & social" class="xl:col-span-2">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-admin.field label="Header button text" for="header_cta_label" hint="Empty keeps “Start your project”.">
                            <input id="header_cta_label" type="text" wire:model="header_cta_label" class="wx-input" placeholder="Start your project">
                        </x-admin.field>
                        <x-admin.field label="Header button link" for="header_cta_url" hint="Where it points. Empty keeps the enquiry form on the same page.">
                            <input id="header_cta_url" type="text" wire:model="header_cta_url" class="wx-input" placeholder="/contact/">
                        </x-admin.field>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        @foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'] as $key => $name)
                            <x-admin.field label="{{ $name }} profile" for="social_{{ $key }}" hint="Full https address — empty hides the icon.">
                                <input id="social_{{ $key }}" type="url" wire:model="social_{{ $key }}" class="wx-input" placeholder="https://{{ $key }}.com/woodexinterior">
                            </x-admin.field>
                        @endforeach
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="Footer">
                <div class="space-y-4">
                    <x-admin.field label="Footer tagline" for="footer_tagline" hint="Overrides the tagline under the logo. Empty keeps the one from Settings → Business.">
                        <input id="footer_tagline" type="text" wire:model="footer_tagline" class="wx-input" placeholder="Interior design, fit-out and furniture by one team.">
                    </x-admin.field>

                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" wire:model="footer_show_services" class="rounded border-gray-300"> “What we do” column
                        </label>
                        <label class="flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" wire:model="footer_show_contact" class="rounded border-gray-300"> Address &amp; phone column
                        </label>
                        <label class="flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" wire:model="footer_show_cities" class="rounded border-gray-300"> “Where we work” row
                        </label>
                    </div>

                    <x-admin.field label="Copyright line" for="footer_copyright" hint="Overrides the bottom line. Empty keeps “© year Legal name. All rights reserved.”.">
                        <input id="footer_copyright" type="text" wire:model="footer_copyright" class="wx-input" placeholder="© {{ date('Y') }} Woodex Interior (Pvt) Ltd.">
                    </x-admin.field>

                    <p class="text-theme-xs text-gray-500">
                        These words and switches reach a page only when the CMS renders it. The site's pages are files
                        under public/, and each file carries its own header and footer — the page builder is where
                        those are edited.
                    </p>
                </div>
            </x-admin.card>

            <x-admin.card title="Tracking" class="xl:col-span-2">
                <div class="space-y-4">
                    <x-admin.field label="Google Analytics 4 measurement ID" for="ga4_id" hint="Analytics → Admin → Data streams. Looks like G-XXXXXXXXXX. Only used when Tag Manager is empty.">
                        <input id="ga4_id" type="text" wire:model="ga4_id" class="wx-input" placeholder="G-XXXXXXXXXX" autocomplete="off">
                    </x-admin.field>

                    <x-admin.field label="Google Tag Manager container" for="gtm_id" hint="Recommended: one container for Analytics, Ads and any other tag, changed without a deploy. Looks like GTM-XXXXXXX.">
                        <input id="gtm_id" type="text" wire:model="gtm_id" class="wx-input" placeholder="GTM-XXXXXXX" autocomplete="off">
                    </x-admin.field>

                    <x-admin.field label="Microsoft Clarity project ID" for="clarity_id" hint="Free session recordings and heatmaps: they show where visitors hesitate on a page.">
                        <input id="clarity_id" type="text" wire:model="clarity_id" class="wx-input" autocomplete="off">
                    </x-admin.field>

                    <x-admin.field label="Google Search Console verification" for="google_site_verification" hint="Search Console → URL prefix → HTML tag: paste the content of the meta tag here (P-13).">
                        <input id="google_site_verification" type="text" wire:model="google_site_verification" class="wx-input" autocomplete="off">
                    </x-admin.field>

                    <label class="flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model="tracking_enabled" class="rounded border-gray-300">
                        Load analytics on the public website
                    </label>

                    <label class="flex items-start gap-2 text-theme-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model="chat_widget_enabled" class="mt-1 rounded border-gray-300">
                        <span>
                            Web chat widget on the public site
                            <span class="block text-theme-xs text-gray-500">A chat bubble on every page. A rule-based flow asks for name, number and service, creates the lead, alerts the team, and the team replies from Conversations — no WhatsApp number needed. Off by default (A0).</span>
                        </span>
                    </label>
                </div>
            </x-admin.card>

            <x-admin.card title="What is live">
                <ul class="space-y-2 text-theme-sm">
                    @foreach ([['Google Analytics 4', $live['ga4']], ['Google Tag Manager', $live['gtm']], ['Microsoft Clarity', $live['clarity']], ['Search Console verified', $live['gsc']]] as [$name, $on])
                        <li class="flex items-center justify-between gap-3">
                            <span class="text-gray-600 dark:text-gray-300">{{ $name }}</span>
                            <x-admin.badge :color="$on ? 'success' : 'gray'" :dot="true">{{ $on ? 'Connected' : 'Not set' }}</x-admin.badge>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-theme-xs text-gray-500">
                    Events sent to Analytics (docs/09 §11.3): enquiry form sent, call tapped, WhatsApp opened, estimator used, visit booked.
                    Mark them as conversions in GA4 → Events.
                </p>
                <div class="mt-6 border-t border-gray-100 pt-4 dark:border-gray-800">
                    <x-admin.button type="submit" icon="save">Save everything above</x-admin.button>
                </div>
            </x-admin.card>
        </form>
    </div>
</div>
