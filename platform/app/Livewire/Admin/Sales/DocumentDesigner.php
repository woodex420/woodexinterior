<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Sales;

use App\Domain\Sales\Actions\SaveDocumentDesign;
use App\Domain\Sales\Models\DocumentDesign;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts::admin')]
#[Title('Document design')]
class DocumentDesigner extends Component
{
    use InteractsWithAdmin, WithFileUploads;

    public string $line = 'interior';

    #[Locked]
    public string $loadedLine = 'interior';

    #[Locked]
    public int $revision = 1;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?TemporaryUploadedFile $logo = null;

    public bool $removeLogo = false;

    public function mount(): void
    {
        $this->authorize('settings.manage');
        $this->loadDesign();
    }

    public function updatedLine(): void
    {
        $this->authorize('settings.manage');
        $this->loadDesign();
    }

    public function save(): void
    {
        $this->authorize('settings.manage');
        $this->validate(['line' => ['required', 'in:interior,furniture'], 'form.brand_name' => ['required', 'string', 'max:35'], 'form.accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'form.header_text' => ['present', 'string', 'max:100'], 'form.footer_text' => ['present', 'string', 'max:120'], 'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:96', 'dimensions:min_width=8,min_height=8,max_width=600,max_height=240']]);
        abort_unless($this->line === $this->loadedLine, 409, 'Reload the selected brand before saving.');
        app(SaveDocumentDesign::class)->handle($this->user(), $this->loadedLine, $this->form, $this->revision, $this->logo, $this->removeLogo);
        $this->loadDesign();
        $this->toast('Design saved for new documents. Existing quotations keep their copied design.');
    }

    private function loadDesign(): void
    {
        abort_unless(in_array($this->line, ['interior', 'furniture'], true), 422);
        $design = DocumentDesign::query()->where('business_line', $this->line)->firstOrFail();
        $this->loadedLine = $this->line;
        $this->revision = $design->revision;
        $this->form = $design->only(['brand_name', 'accent_color', 'header_text', 'footer_text']);
        $this->logo = null;
        $this->removeLogo = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.admin.sales.document-designer', ['design' => DocumentDesign::query()->where('business_line', $this->loadedLine)->firstOrFail()]);
    }
}
