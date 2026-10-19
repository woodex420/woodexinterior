<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Models\SavedSection;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The section library, standalone (the old admin's own screen): every section the studio saved from a
 * page, managed outside the editor — renamed, previewed and retired here. The editor's own shelf stays
 * the place sections are saved from and dropped onto pages (V2-2); this screen is the librarian.
 */
#[Layout('layouts::admin')]
#[Title('Section library')]
class SectionLibraryIndex extends Component
{
    use InteractsWithAdmin;

    /** The rename modal: its target section, its visible flag and the value being typed. */
    public ?int $renameId = null;

    public bool $showRename = false;

    public string $renameName = '';

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return ['renameName' => ['required', 'string', 'max:120']];
    }

    public function rename(int $id): void
    {
        $this->renameId = $id;
        $this->renameName = (string) SavedSection::query()->findOrFail($id)->name;
    }

    public function saveRename(): void
    {
        $this->validateOnly('renameName');

        $saved = SavedSection::query()->findOrFail((int) $this->renameId);
        $saved->update(['name' => trim($this->renameName)]);

        $this->renameId = null;
        $this->renameName = '';
        $this->toast('Renamed to "'.trim($saved->name).'".');
    }

    public function delete(int $id): void
    {
        SavedSection::query()->findOrFail($id)->delete();
        $this->toast('Section removed from the library. Pages already using it keep their copy.');
    }

    public function render(): View
    {
        return view('livewire.admin.website.section-library-index', [
            'sections' => SavedSection::query()->with('creator')->orderBy('name')->get(),
        ]);
    }
}
