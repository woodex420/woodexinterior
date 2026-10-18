<div x-data="{ open: false, url: '', label: 'Document preview' }"
     x-on:document-preview.window="url = $event.detail.url; label = $event.detail.label || 'Document preview'; open = true"
     x-on:keydown.escape.window="open = false">
    <template x-if="open">
        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/60 p-2 sm:p-5" role="dialog" aria-modal="true" aria-label="Document preview" x-trap.inert.noscroll="open" x-on:click.self="open = false">
            <div class="flex h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 p-3 dark:border-gray-800">
                    <h2 class="truncate font-medium text-gray-900 dark:text-white" x-text="label"></h2>
                    <div class="flex shrink-0 items-center gap-3"><a :href="url" download class="text-theme-sm text-brand-600">Download PDF</a><button type="button" x-on:click="open = false" class="rounded-lg px-3 py-2 text-theme-sm text-gray-700 dark:text-gray-200" aria-label="Close document preview">Close</button></div>
                </div>
                <p class="px-3 py-2 text-theme-xs text-gray-500">PDF preview stays here. If your browser cannot display it, use Download PDF. No document is sent by previewing.</p>
                <iframe :src="url" :title="label" class="min-h-0 w-full flex-1 border-0 bg-gray-100"></iframe>
            </div>
        </div>
    </template>
</div>
