<div>
    <x-admin.page-header title="Blog"
                         description="Every page of type “insight” — written in the same block editor as the rest of the site, published under /insights/."
                         :breadcrumbs="['Website' => null, 'Blog' => null]">
        <x-slot:actions>
            @if ($listing)
                <x-admin.button variant="ghost" :href="$listing->canonical()" target="_blank" rel="noopener">View blog</x-admin.button>
            @endif
            <x-admin.button icon="plus" wire:click="$set('showNew', true)">New post</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative min-w-0 flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                <x-icon name="search" class="size-4" />
            </span>
            <input type="search" wire:model.live.debounce.300ms="search" class="wx-input pl-9"
                   placeholder="Search a post by title, URL or summary…" aria-label="Search posts">
        </div>
        <div class="shrink-0 sm:w-52">
            <select wire:model.live="status" class="wx-input" aria-label="Filter by status">
                <option value="">All posts</option>
                <option value="published">Published ({{ $published }})</option>
                <option value="draft">Drafts ({{ $drafts }})</option>
            </select>
        </div>
    </div>

    @if ($posts->isEmpty())
        <x-admin.empty icon="book" title="No posts here yet"
                       description="Write the first one — the URL and the opening sections are prepared for you.">
            <x-admin.button icon="plus" wire:click="$set('showNew', true)">New post</x-admin.button>
        </x-admin.empty>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="min-w-full divide-y divide-gray-100 text-theme-sm dark:divide-gray-800">
                <thead class="bg-gray-50 text-left text-theme-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-medium">Post</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Index</th>
                        <th class="px-5 py-3 font-medium">Updated</th>
                        <th class="px-5 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($posts as $post)
                        @php $onIndex = in_array(rtrim($post->slug, '/').'/', $listed, true); @endphp
                        <tr wire:key="post-{{ $post->id }}" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.website.pages.edit', $post) }}" wire:navigate
                                   class="font-medium text-navy-800 hover:underline dark:text-white/90">{{ $post->title }}</a>
                                <span class="mt-0.5 block text-theme-xs text-gray-400">{{ $post->slug }}</span>
                                @if ($post->excerpt)
                                    <span class="mt-1 line-clamp-1 block max-w-xl text-theme-xs text-gray-500">{{ $post->excerpt }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($post->status === \App\Domain\Website\Enums\PageStatus::Published)
                                    <span class="rounded-full bg-success-50 px-2.5 py-1 text-theme-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Published</span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">Draft</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($onIndex)
                                    <span class="rounded-full bg-success-50 px-2.5 py-1 text-theme-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">On /insights/</span>
                                    @can('website.publish')
                                        <button type="button" wire:click="toggleIndex({{ $post->id }})"
                                                class="mt-1 block text-theme-xs text-gray-500 hover:text-gray-800 dark:text-gray-400">Hide</button>
                                    @endcan
                                @elseif ($post->status === \App\Domain\Website\Enums\PageStatus::Published)
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">Not listed</span>
                                    @can('website.publish')
                                        <button type="button" wire:click="toggleIndex({{ $post->id }})"
                                                class="mt-1 block text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Show on /insights/</button>
                                    @endcan
                                @else
                                    <span class="text-theme-xs text-gray-400">Draft</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-500">{{ $post->updated_at?->diffForHumans() }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($post->status === \App\Domain\Website\Enums\PageStatus::Published)
                                        <a href="{{ $post->canonical() }}" target="_blank" rel="noopener"
                                           class="text-theme-xs font-medium text-gray-600 hover:text-brand-600 dark:text-gray-300">View</a>
                                    @endif
                                    <a href="{{ route('admin.website.pages.edit', $post) }}" wire:navigate
                                       class="text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Edit</a>
                                    <button type="button" wire:click="duplicate({{ $post->id }})"
                                            class="text-theme-xs font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400">Duplicate</button>
                                    @can('website.publish')
                                        @if ($post->status === \App\Domain\Website\Enums\PageStatus::Published)
                                            <button type="button" wire:click="unpublish({{ $post->id }})"
                                                    wire:confirm="Take “{{ $post->title }}” off the site?"
                                                    class="text-theme-xs font-medium text-error-600 hover:text-error-700">Unpublish</button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-5">{{ $posts->links('components.admin.pagination') }}</div>
    @endif

    @if ($showNew)
        <div class="fixed inset-0 z-[9999] flex items-end justify-center bg-gray-900/50 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="new-post-title"
             x-data x-on:keydown.escape.window="$wire.set('showNew', false)">
            <form wire:submit="createPost" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-theme-lg dark:bg-gray-900">
                <div class="mb-5 flex items-start justify-between">
                    <div>
                        <h2 id="new-post-title" class="text-theme-xl font-medium text-gray-900 dark:text-white">New post</h2>
                        <p class="text-theme-xs text-gray-500">A draft is created with its URL and two sections; nothing is public until you publish.</p>
                    </div>
                    <button type="button" wire:click="$set('showNew', false)" class="text-gray-400 hover:text-gray-600" aria-label="Close"><x-icon name="x" /></button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="wx-label" for="newPostTitle">Title</label>
                        <input id="newPostTitle" type="text" wire:model.live.debounce.200ms="newTitle" class="wx-input" autofocus
                               placeholder="e.g. Kitchen cost in Lahore: what drives the price">
                        @error('newTitle') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="wx-label" for="newPostSlug">URL</label>
                        <div class="flex items-stretch">
                            <span class="flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-theme-sm text-gray-500 dark:border-gray-700 dark:bg-white/5">/insights/</span>
                            <input id="newPostSlug" type="text" wire:model="newSlug" class="wx-input rounded-l-none" placeholder="kitchen-cost-lahore">
                        </div>
                        @error('newSlug') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="wx-label" for="newPostExcerpt">Summary <span class="text-gray-400">(shown in listings and search results)</span></label>
                        <textarea id="newPostExcerpt" rows="3" wire:model="newExcerpt" class="wx-input h-auto py-2"
                                  placeholder="One or two sentences a reader would click on."></textarea>
                        @error('newExcerpt') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-admin.button variant="secondary" wire:click="$set('showNew', false)">Cancel</x-admin.button>
                    <x-admin.button type="submit" icon="plus" wire:loading.attr="disabled" wire:target="createPost">Create draft</x-admin.button>
                </div>
            </form>
        </div>
    @endif
</div>
