{{--
    The enquiry form. Plain HTML, visible and usable before/without JavaScript: Alpine only takes over the submit
    (D-49). Posts to the public lead API, so the enquiry becomes a CRM lead with attribution.
--}}
<form id="enquiry" x-data="leadForm()" @submit.prevent="submit" class="rounded-2xl bg-white p-6 ring-1 ring-line md:p-8">
    <div x-show="!done" class="space-y-4">
        <h2 class="text-2xl">Start your project</h2>
        <p class="text-sm text-muted">Share the location, size and timeline. We reply within one working day.</p>
        <input type="text" name="company_website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        <div>
            <label class="mb-1 block text-sm text-ink" for="lf-name">Name</label>
            <input id="lf-name" name="name" required autocomplete="name" class="h-11 w-full rounded-ui border border-line px-3 text-ink">
            <p class="mt-1 text-xs text-red-600" x-text="errors.name?.[0]"></p>
        </div>
        <div>
            <label class="mb-1 block text-sm text-ink" for="lf-phone">Phone / WhatsApp</label>
            <input id="lf-phone" name="phone" type="tel" required autocomplete="tel" placeholder="0322 4000768" class="h-11 w-full rounded-ui border border-line px-3 text-ink">
            <p class="mt-1 text-xs text-red-600" x-text="errors.phone?.[0]"></p>
        </div>
        <div>
            <label class="mb-1 block text-sm text-ink" for="lf-service">Service</label>
            <select id="lf-service" name="service" class="h-11 w-full rounded-ui border border-line bg-white px-3 text-ink">
                <option value="interior-design">Interior design</option>
                <option value="fit-out">Fit-out</option>
                <option value="renovation">Renovation</option>
                <option value="architecture">Architecture</option>
                <option value="3d-studio">3D visualisation</option>
                <option value="furniture">Custom furniture</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm text-ink" for="lf-message">Message</label>
            <textarea id="lf-message" name="message" rows="3" class="w-full rounded-ui border border-line px-3 py-2 text-ink"></textarea>
        </div>
        <label class="flex items-start gap-2 text-xs text-muted">
            <input type="checkbox" name="consent" class="mt-0.5"> I agree to be contacted by Woodex on WhatsApp, phone or email about my enquiry.
        </label>
        <p class="text-sm text-red-600" x-cloak x-show="error" x-text="error"></p>
        {{-- Disabled until Alpine starts: without JS the form cannot reach the JSON API (D-13). --}}
        <button type="submit" disabled :disabled="sending" class="flex h-11 w-full items-center justify-center rounded-full bg-ink text-sm font-medium text-white hover:bg-navy-900 disabled:opacity-60">
            <span x-text="sending ? 'Sending…' : 'Send enquiry'">Send enquiry</span>
        </button>
        <noscript><p class="text-xs text-red-600">Please turn on JavaScript to send this form.</p></noscript>
    </div>
    <div x-cloak x-show="done" class="py-6 text-center" role="status">
        <h2 class="text-2xl">Thank you!</h2>
        <p class="mt-3">Your reference is <strong x-text="reference"></strong>. Our team will contact you shortly.</p>
        <a :href="whatsappUrl" x-show="whatsappUrl" target="_blank" rel="noopener" class="mt-6 inline-flex h-11 items-center rounded-full bg-ink px-6 text-sm font-medium text-white">Continue on WhatsApp</a>
    </div>
</form>
