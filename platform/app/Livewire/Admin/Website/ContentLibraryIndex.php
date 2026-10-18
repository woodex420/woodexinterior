<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Models\ContentEntry;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Services\ContentLibrary;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The shared content library (V2-3 F6, docs/14 §5a): the studio's answers, reviews and client logos,
 * written once and placed on any page by its FAQ, Reviews or Clients section. The library's order is
 * the order everywhere — pages pick members, never sequence.
 */
#[Layout('layouts::admin')]
#[Title('Content')]
class ContentLibraryIndex extends Component
{
    use InteractsWithAdmin;

    public string $kind = ContentEntry::FAQ;

    /** The add-form fields, one per kind (the shape the matching section renders). */
    public string $question = '';

    public string $answer = '';

    public string $text = '';

    public string $name = '';

    public string $when = '';

    public string $source = '';

    public ?int $mediaId = null;

    public string $alt = '';

    public function mount(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $this->kind = in_array($this->kind, ContentEntry::KINDS, true) ? $this->kind : ContentEntry::FAQ;
    }

    public function updatedKind(): void
    {
        $this->resetExcept(['kind']);
    }

    public function add(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $data = $this->clean();

        ContentEntry::query()->create([
            'kind' => $this->kind,
            'data' => $data,
            'position' => (int) ContentEntry::query()->ofKind($this->kind)->max('position') + 1,
        ]);

        $this->resetExcept(['kind']);
        ContentLibrary::flush();
        $this->toast('Added to the library.');
    }

    public function remove(int $entryId): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        ContentEntry::query()->findOrFail($entryId)->delete();
        ContentLibrary::flush();
        $this->toast('Removed from the library.');
    }

    public function move(int $entryId, int $direction): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $entry = ContentEntry::query()->findOrFail($entryId);
        $sibling = ContentEntry::query()
            ->ofKind($entry->kind)
            ->where('position', $entry->position + $direction)
            ->first();

        if ($sibling === null) {
            return;
        }

        [$entry->position, $sibling->position] = [$sibling->position, $entry->position];
        $entry->save();
        $sibling->save();

        ContentLibrary::flush();
    }

    public function render(): View
    {
        return view('livewire.admin.website.content-library', [
            'entries' => ContentEntry::query()->ofKind($this->kind)->orderBy('position')->get(),
            'media' => Media::query()->orderByDesc('id')->get(['id', 'title', 'alt', 'path']),
        ]);
    }

    /**
     * The entry's data for its kind — validated per kind (validation failures throw, so the
     * caller only ever sees a ready entry).
     *
     * @return array<string, mixed>
     */
    private function clean(): array
    {
        if ($this->kind === ContentEntry::FAQ) {
            $this->validate(['question' => ['required', 'string', 'max:400'], 'answer' => ['required', 'string', 'max:2000']]);

            return ['question' => trim($this->question), 'answer' => trim($this->answer)];
        }

        if ($this->kind === ContentEntry::TESTIMONIAL) {
            $this->validate(['text' => ['required', 'string', 'max:1000'], 'name' => ['required', 'string', 'max:120']]);

            return ['text' => trim($this->text), 'name' => trim($this->name), 'when' => trim($this->when), 'source' => trim($this->source)];
        }

        $this->validate(['mediaId' => ['required', 'integer', 'exists:website_media,id'], 'alt' => ['required', 'string', 'max:200']]);

        return ['media_id' => $this->mediaId, 'alt' => trim($this->alt)];
    }
}
