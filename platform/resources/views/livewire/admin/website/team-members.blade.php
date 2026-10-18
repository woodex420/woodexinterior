<div>
    <x-admin.page-header title="Team" description="The studio's people — a photo, a role and a short bio each. Managed once here; a page's Team section picks members and shows them in this order, everywhere."
                         :breadcrumbs="['Website' => null, 'Team' => null]" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-admin.card title="Add a member">
            <form wire:submit="add" class="space-y-3">
                <x-admin.field label="Name" for="name">
                    <input id="name" type="text" wire:model="name" class="wx-input" placeholder="The founder">
                </x-admin.field>
                <x-admin.field label="Role" for="role">
                    <input id="role" type="text" wire:model="role" class="wx-input" placeholder="Founder & lead designer">
                </x-admin.field>
                <x-admin.field label="Bio" for="bio" hint="Two or three plain sentences — what they do, how they work.">
                    <textarea id="bio" rows="3" wire:model="bio" class="wx-input" placeholder="Fifteen years of interiors across Lahore."></textarea>
                </x-admin.field>
                <x-admin.field label="Photo" for="mediaId" hint="Picked from Website → Media.">
                    <select id="mediaId" wire:model.live="mediaId" class="wx-input">
                        <option value="">No photo yet…</option>
                        @foreach ($media as $image)
                            <option value="{{ $image->id }}">{{ $image->title ?: basename($image->path) }}</option>
                        @endforeach
                    </select>
                </x-admin.field>

                <x-admin.button type="submit" icon="plus">Add</x-admin.button>
            </form>
        </x-admin.card>

        <x-admin.card :padding="false" title="The team, in the studio's order" class="xl:col-span-2">
            <table class="min-w-full text-left text-theme-sm">
                <thead class="border-b border-gray-100 text-theme-xs tracking-wide text-gray-500 uppercase dark:border-gray-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Member</th>
                        <th class="hidden px-5 py-3 font-medium md:table-cell">Role</th>
                        <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($members as $member)
                        <tr wire:key="member-{{ $member->id }}" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-5 py-3 font-medium text-gray-800 dark:text-white/90">
                                {{ $member->name }}
                                @if (! empty($member->bio))<span class="block text-theme-xs font-normal text-gray-400">{{ \Illuminate\Support\Str::limit($member->bio, 90) }}</span>@endif
                            </td>
                            <td class="hidden px-5 py-3 text-theme-xs text-gray-500 md:table-cell">{{ $member->role }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" wire:click="move({{ $member->id }}, -1)" class="wx-mini-btn" aria-label="Move up" @if ($loop->first) disabled @endif>↑</button>
                                    <button type="button" wire:click="move({{ $member->id }}, 1)" class="wx-mini-btn" aria-label="Move down" @if ($loop->last) disabled @endif>↓</button>
                                    <button type="button" wire:click="remove({{ $member->id }})" wire:confirm="Remove this member? Pages showing the team simply show one less person." class="text-theme-sm text-error-600 hover:underline">Remove</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <x-admin.empty icon="users" title="No members yet" description="Add the first one on the left — any page's Team section can then pick them." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-admin.card>
    </div>
</div>
