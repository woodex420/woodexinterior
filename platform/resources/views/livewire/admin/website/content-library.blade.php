<div>
    <x-admin.page-header title="Content" description="The studio's shared answers, reviews and client logos. Written once here, placed on any page by its FAQ, Reviews or Clients section — in this order, everywhere."
                         :breadcrumbs="['Website' => null, 'Content' => null]" />

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach (['faq' => 'FAQs', 'testimonial' => 'Testimonials', 'logo' => 'Client logos'] as $value => $label)
            <button type="button" wire:click="$set('kind', '{{ $value }}')"
                    @class(['wx-mini-btn' => true, 'border-brand-500 text-brand-700 dark:text-brand-300' => $kind === $value])>{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-admin.card title="Add to the library">
            <form wire:submit="add" class="space-y-3">
                @if ($kind === 'faq')
                    <x-admin.field label="Question" for="question">
                        <input id="question" type="text" wire:model="question" class="wx-input" placeholder="Do you work outside Lahore?">
                    </x-admin.field>
                    <x-admin.field label="Answer" for="answer">
                        <textarea id="answer" rows="3" wire:model="answer" class="wx-input" placeholder="Yes, across Pakistan."></textarea>
                    </x-admin.field>
                @elseif ($kind === 'testimonial')
                    <x-admin.field label="Review" for="text" hint="Quoted verbatim — never rewritten, never invented.">
                        <textarea id="text" rows="3" wire:model="text" class="wx-input" placeholder="They finished on the date they promised."></textarea>
                    </x-admin.field>
                    <x-admin.field label="Client's name" for="name">
                        <input id="name" type="text" wire:model="name" class="wx-input" placeholder="A client, DHA Lahore">
                    </x-admin.field>
                    <div class="grid grid-cols-2 gap-3">
                        <x-admin.field label="When" for="when">
                            <input id="when" type="text" wire:model="when" class="wx-input" placeholder="March 2026">
                        </x-admin.field>
                        <x-admin.field label="Source" for="source">
                            <input id="source" type="text" wire:model="source" class="wx-input" placeholder="Google">
                        </x-admin.field>
                    </div>
                @else
                    <x-admin.field label="Logo" for="mediaId" hint="Picked from Website → Media.">
                        <select id="mediaId" wire:model.live="mediaId" class="wx-input">
                            <option value="">Choose an image…</option>
                            @foreach ($media as $image)
                                <option value="{{ $image->id }}">{{ $image->title ?: basename($image->path) }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                    <x-admin.field label="Alt text" for="alt" hint="The client's name, as screen readers say it.">
                        <input id="alt" type="text" wire:model="alt" class="wx-input" placeholder="DHA Lahore">
                    </x-admin.field>
                @endif

                <x-admin.button type="submit" icon="plus">Add</x-admin.button>
            </form>
        </x-admin.card>

        <x-admin.card :padding="false" title="{{ ['faq' => 'FAQs', 'testimonial' => 'Testimonials', 'logo' => 'Client logos'][$kind] }}" class="xl:col-span-2">
            <table class="min-w-full text-left text-theme-sm">
                <thead class="border-b border-gray-100 text-theme-xs tracking-wide text-gray-500 uppercase dark:border-gray-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">{{ $kind === 'faq' ? 'Question' : ($kind === 'testimonial' ? 'Review' : 'Logo') }}</th>
                        @if ($kind === 'faq')<th class="hidden px-5 py-3 font-medium md:table-cell">Answer</th>@endif
                        @if ($kind === 'testimonial')<th class="hidden px-5 py-3 font-medium md:table-cell">Client</th>@endif
                        @if ($kind === 'logo')<th class="hidden px-5 py-3 font-medium md:table-cell">Alt text</th>@endif
                        <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($entries as $entry)
                        <tr wire:key="entry-{{ $entry->id }}" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-5 py-3 font-medium text-gray-800 dark:text-white/90">{{ $kind === 'faq' ? $entry->data['question'] : ($kind === 'testimonial' ? '“'.\Illuminate\Support\Str::limit($entry->data['text'], 90).'”' : ($entry->data['alt'] ?? '')) }}</td>
                            <td class="hidden px-5 py-3 text-theme-xs text-gray-500 md:table-cell">
                                @if ($kind === 'faq')
                                    {{ \Illuminate\Support\Str::limit($entry->data['answer'], 100) }}
                                @elseif ($kind === 'testimonial')
                                    {{ $entry->data['name'] ?? '' }}@if (! empty($entry->data['when'])) · {{ $entry->data['when'] }}@endif
                                @else
                                    {{ basename((string) \App\Domain\Website\Models\Media::query()->find($entry->data['media_id'])?->path ?? '') }}
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" wire:click="move({{ $entry->id }}, -1)" class="wx-mini-btn" aria-label="Move up" @if ($loop->first) disabled @endif>↑</button>
                                    <button type="button" wire:click="move({{ $entry->id }}, 1)" class="wx-mini-btn" aria-label="Move down" @if ($loop->last) disabled @endif>↓</button>
                                    <button type="button" wire:click="remove({{ $entry->id }})" wire:confirm="Remove this from the library? Pages that use it simply show one less." class="text-theme-sm text-error-600 hover:underline">Remove</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <x-admin.empty icon="book" title="Nothing here yet" description="Add the first entry on the left — the FAQ, Reviews and Clients sections of any page can then pick it." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-admin.card>
    </div>
</div>
