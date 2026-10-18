<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\TeamMember;
use App\Domain\Website\Services\ContentLibrary;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The studio's team (V2-3 F7, docs/14 §5a): members with a photo, a role and a short bio, managed
 * once here. A page's Team section picks members and shows them in this order — pages choose
 * people, never sequence. The order is the studio's, so the page shows the founder first wherever
 * the founder is shown.
 */
#[Layout('layouts::admin')]
#[Title('Team')]
class TeamMembersIndex extends Component
{
    use InteractsWithAdmin;

    public string $name = '';

    public string $role = '';

    public string $bio = '';

    public ?int $mediaId = null;

    public function mount(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);
    }

    public function add(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'mediaId' => ['nullable', 'integer', 'exists:website_media,id'],
        ]);

        TeamMember::query()->create([
            'name' => trim($this->name),
            'role' => trim($this->role),
            'bio' => trim($this->bio) !== '' ? trim($this->bio) : null,
            'media_id' => $this->mediaId,
            'position' => (int) TeamMember::query()->max('position') + 1,
        ]);

        $this->reset('name', 'role', 'bio', 'mediaId');
        ContentLibrary::flush();
        $this->toast('Added to the team.');
    }

    public function remove(int $memberId): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        TeamMember::query()->findOrFail($memberId)->delete();
        ContentLibrary::flush();
        $this->toast('Removed from the team.');
    }

    public function move(int $memberId, int $direction): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $member = TeamMember::query()->findOrFail($memberId);
        $sibling = TeamMember::query()->where('position', $member->position + $direction)->first();

        if ($sibling === null) {
            return;
        }

        [$member->position, $sibling->position] = [$sibling->position, $member->position];
        $member->save();
        $sibling->save();

        ContentLibrary::flush();
    }

    public function render(): View
    {
        return view('livewire.admin.website.team-members', [
            'members' => TeamMember::query()->ordered()->get(),
            'media' => Media::query()->orderByDesc('id')->get(['id', 'title', 'alt', 'path']),
        ]);
    }
}
