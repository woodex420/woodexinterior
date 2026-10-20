<div>
    <x-admin.page-header title="Portfolio"
                         description="Every page of type “project”, worked as case studies — same block editor as the rest of the site, published under /projects/."
                         :breadcrumbs="['Website' => null, 'Portfolio' => null]">
        <x-slot:actions>
            @if ($listing)
                <x-admin.button variant="ghost" :href="$listing->canonical()" target="_blank" rel="noopener">View portfolio</x-admin.button>
            @endif
            <x-admin.button icon="plus" wire:click="$set('showNew', true)">New study</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative min-w-0 flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                <x-icon name="search" class="size-4" />
            </span>
            <input type="search" wire:model.live.debounce.300ms="search" class="wx-input pl-9"
                   placeholder="Search a study by title, URL or summary…" aria-label="Search studies">
        </div>
        <div class="shrink-0 sm:w-52">
            <select wire:model.live="status" class="wx-input" aria-label="Filter by status">
                <option value="">All studies</option>
                <option value="published">Published ({{ $published }})</option>
                <option value="draft">Drafts ({{ $drafts }})</option>
            </select>
        </div>
    </div>

    @if ($studies->isEmpty())
        <x-admin.empty icon="layout" title="No studies here yet"
                       description="Create the first one — the URL and the opening sections are prepared for you.">
            <x-admin.button icon="plus" wire:click="$set('showNew', true)">New study</x-admin.button>
        </x-admin.empty>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="min-w-full divide-y divide-gray-100 text-theme-sm dark:divide-gray-800">
                <thead class="bg-gray-50 text-left text-theme-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-medium">Study</th>
                        <th class="px-5 py-3 font-medium">Project</th>
                        <th class="px-5 py-3 font-medium">Index</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Updated</th>
                        <th class="px-5 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($studies as $study)
                        @php
                            $project = $linked->get($study->id);
                            $onIndex = in_array(rtrim($study->slug, '/').'/', $listed, true);
                        @endphp
                        <tr wire:key="study-{{ $study->id }}" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.website.pages.edit', $study) }}" wire:navigate
                                   class="font-medium text-navy-800 hover:underline dark:text-white/90">{{ $study->title }}</a>
                                <span class="mt-0.5 block text-theme-xs text-gray-400">{{ $study->slug }}</span>
                                @if ($study->excerpt)
                                    <span class="mt-1 line-clamp-1 block max-w-md text-theme-xs text-gray-500">{{ $study->excerpt }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($project)
                                    <span class="block font-medium text-gray-800 dark:text-gray-200">{{ $project->code }}</span>
                                    <span class="block max-w-40 truncate text-theme-xs text-gray-500">{{ $project->name }}</span>
                                @else
                                    <span class="text-theme-xs text-gray-500">Design study</span>
                                @endif
                                <button type="button" wire:click="openLink({{ $study->id }})"
                                        class="mt-1 block text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                                    {{ $project ? 'Change' : 'Link a project' }}
                                </button>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($onIndex)
                                    <span class="rounded-full bg-success-50 px-2.5 py-1 text-theme-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">On /projects/</span>
                                    @can('website.publish')
                                        <button type="button" wire:click="toggleIndex({{ $study->id }})"
                                                class="mt-1 block text-theme-xs text-gray-500 hover:text-gray-800 dark:text-gray-400">Hide</button>
                                    @endcan
                                @elseif ($study->status === \App\Domain\Website\Enums\PageStatus::Published)
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">Not listed</span>
                                    @can('website.publish')
                                        <button type="button" wire:click="toggleIndex({{ $study->id }})"
                                                class="mt-1 block text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Show on /projects/</button>
                                    @endcan
                                @else
                                    <span class="text-theme-xs text-gray-400">Draft</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($study->status === \App\Domain\Website\Enums\PageStatus::Published)
                                    <span class="rounded-full bg-success-50 px-2.5 py-1 text-theme-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Published</span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">Draft</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-500">{{ $study->updated_at?->diffForHumans() }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($study->status === \App\Domain\Website\Enums\PageStatus::Published)
                                        <a href="{{ $study->canonical() }}" target="_blank" rel="noopener"
                                           class="text-theme-xs font-medium text-gray-600 hover:text-brand-600 dark:text-gray-300">View</a>
                                    @endif
                                    <a href="{{ route('admin.website.pages.edit', $study) }}" wire:navigate
                                       class="text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Edit</a>
                                    <button type="button" wire:click="duplicate({{ $study->id }})"
                                            class="text-theme-xs font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400">Duplicate</button>
                                    @can('website.publish')
                                        @if ($study->status === \App\Domain\Website\Enums\PageStatus::Published)
                                            <button type="button" wire:click="unpublish({{ $study->id }})"
                                                    wire:confirm="Take “{{ $study->title }}” off the site? It also leaves the /projects/ index."
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

        <div class="mt-5">{{ $studies->links('components.admin.pagination') }}</div>
    @endif

    @if ($showNew)
        <div class="fixed inset-0 z-[9999] flex items-end justify-center bg-gray-900/50 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="new-study-title"
             x-data x-on:keydown.escape.window="$wire.set('showNew', false)">
            <form wire:submit="createStudy" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-theme-lg dark:bg-gray-900">
                <div class="mb-5 flex items-start justify-between">
                    <div>
                        <h2 id="new-study-title" class="text-theme-xl font-medium text-gray-900 dark:text-white">New study</h2>
                        <p class="text-theme-xs text-gray-500">A draft is created with its URL and two sections. Publish it, then show it on /projects/.</p>
                    </div>
                    <button type="button" wire:click="$set('showNew', false)" class="text-gray-400 hover:text-gray-600" aria-label="Close"><x-icon name="x" /></button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="wx-label" for="newStudyTitle">Title</label>
                        <input id="newStudyTitle" type="text" wire:model.live.debounce.200ms="newTitle" class="wx-input" autofocus
                               placeholder="e.g. A clinic that calms">
                        @error('newTitle') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="wx-label" for="newStudySlug">URL</label>
                        <div class="flex items-stretch">
                            <span class="flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-theme-sm text-gray-500 dark:border-gray-700 dark:bg-white/5">/projects/</span>
                            <input id="newStudySlug" type="text" wire:model="newSlug" class="wx-input rounded-l-none" placeholder="clinic-fit-out">
                        </div>
                        @error('newSlug') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="wx-label" for="newStudyExcerpt">Summary <span class="text-gray-400">(shown on cards and in search results)</span></label>
                        <textarea id="newStudyExcerpt" rows="3" wire:model="newExcerpt" class="wx-input h-auto py-2"
                                  placeholder="One or two sentences about the space and the problem."></textarea>
                        @error('newExcerpt') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="wx-label" for="newStudyProject">Linked project <span class="text-gray-400">(optional)</span></label>
                        <select id="newStudyProject" wire:model="newProjectId" class="wx-input">
                            <option value="">Not linked — a design study</option>
                            @foreach ($projects as $option)
                                <option value="{{ $option->id }}">
                                    {{ $option->code }} · {{ $option->name }}{{ $option->city ? ' · '.$option->city : '' }}{{ $option->case_study_consent ? '' : ' (no case-study consent)' }}
                                </option>
                            @endforeach
                        </select>
                        @error('newProjectId') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-theme-xs text-gray-500">Only projects without a case study are offered. A project marked “no consent” should not be published as a client story.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-admin.button variant="secondary" wire:click="$set('showNew', false)">Cancel</x-admin.button>
                    <x-admin.button type="submit" icon="plus" wire:loading.attr="disabled" wire:target="createStudy">Create draft</x-admin.button>
                </div>
            </form>
        </div>
    @endif

    @if ($showLink)
        <div class="fixed inset-0 z-[9999] flex items-end justify-center bg-gray-900/50 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="link-project-title"
             x-data x-on:keydown.escape.window="$wire.set('showLink', false)">
            <form wire:submit="linkProject" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-theme-lg dark:bg-gray-900">
                <div class="mb-5 flex items-start justify-between">
                    <div>
                        <h2 id="link-project-title" class="text-theme-xl font-medium text-gray-900 dark:text-white">Link a project</h2>
                        <p class="text-theme-xs text-gray-500">“{{ $linkTitle }}” — connect this study to the job it came from.</p>
                    </div>
                    <button type="button" wire:click="$set('showLink', false)" class="text-gray-400 hover:text-gray-600" aria-label="Close"><x-icon name="x" /></button>
                </div>

                <div>
                    <label class="wx-label" for="linkProjectSelect">Project</label>
                    <select id="linkProjectSelect" wire:model="linkProjectId" class="wx-input">
                        <option value="">Not linked — a design study</option>
                        @foreach ($projects as $option)
                            <option value="{{ $option->id }}">
                                {{ $option->code }} · {{ $option->name }}{{ $option->case_study_consent ? '' : ' (no case-study consent)' }}
                            </option>
                        @endforeach
                    </select>
                    @error('linkProjectId') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-theme-xs text-gray-500">The project must not already have a case study. Choosing “Not linked” removes the link.</p>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-admin.button variant="secondary" wire:click="$set('showLink', false)">Cancel</x-admin.button>
                    <x-admin.button type="submit" wire:loading.attr="disabled" wire:target="linkProject">Save link</x-admin.button>
                </div>
            </form>
        </div>
    @endif
</div>
