<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Projects;

use App\Domain\Projects\Actions\ChangeProjectStage;
use App\Domain\Projects\Actions\PostProjectUpdate;
use App\Domain\Projects\Actions\RaiseMilestoneInvoice;
use App\Domain\Projects\Actions\ReachProjectMilestone;
use App\Domain\Projects\Actions\ShareProjectUpdate;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectMilestone;
use App\Domain\Projects\Models\ProjectUpdate;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Models\User;
use App\Settings\SalesSettings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\WithPagination;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The workspace for one job (PRJ-002…PRJ-005): where it stands, what is owed, the milestones the money hangs on,
 * the diary from the site with its photographs, and the files.
 *
 * Everything the client is told comes from here, and nothing leaves the studio unless somebody presses Share.
 */
#[Layout('layouts::admin')]
class ProjectShow extends Component
{
    use InteractsWithAdmin, WithFileUploads, WithPagination;

    public Project $project;

    /** @var array<int, TemporaryUploadedFile> */
    public array $photos = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    public string $body = '';

    public ?int $progressPercent = null;

    public ?int $milestoneId = null;

    public bool $shareWithClient = false;

    public string $notes = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        $this->progressPercent = (int) $project->progress_percent;
        $this->notes = (string) $project->notes;
    }

    public function setStage(string $value): void
    {
        $this->authorize('update', $this->project);

        $stage = ProjectStage::tryFrom($value);

        if ($stage === null) {
            return;
        }

        app(ChangeProjectStage::class)->handle($this->project, $stage, $this->user());
        $this->project->refresh();

        $this->toast($this->project->code.' is now at '.$stage->label().'.');
    }

    public function setStatus(string $value): void
    {
        $this->authorize('update', $this->project);

        $status = ProjectStatus::tryFrom($value);

        if ($status === null) {
            return;
        }

        $this->project->forceFill([
            'status' => $status,
            'completed_at' => $status === ProjectStatus::Completed ? ($this->project->completed_at ?? now()) : null,
        ])->save();

        $this->project->refresh();
        $this->toast($this->project->code.' is '.$status->label().'.');
    }

    /** Save the site address, the target date and the internal note. */
    public function saveDetails(): void
    {
        $this->authorize('update', $this->project);

        $this->project->forceFill(['notes' => $this->notes])->save();
        $this->toast('Project details saved.');
    }

    public function toggleMilestone(int $milestoneId): void
    {
        $this->authorize('update', $this->project);

        $milestone = $this->project->milestones()->findOrFail($milestoneId);
        $reached = $milestone->reached_at === null;

        app(ReachProjectMilestone::class)->handle($milestone, $this->user(), $reached);

        $this->project->refresh();
        $this->progressPercent = (int) $this->project->progress_percent;

        $this->toast($milestone->name.($reached ? ' reached.' : ' reopened.'));
    }

    public function raiseInvoice(int $milestoneId): void
    {
        $this->authorize('update', $this->project);

        $milestone = $this->project->milestones()->findOrFail($milestoneId);
        $invoice = app(RaiseMilestoneInvoice::class)->handle($milestone, $this->user());

        $this->project->refresh();
        $this->toast('Draft invoice '.$invoice->number.' is ready — check it and send it from Sales → Invoices.');
    }

    public function post(): void
    {
        $this->authorize('update', $this->project);



        $this->validate([
            'body' => ['required', 'string', 'min:2', 'max:4000'],
            'progressPercent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'photos' => ['array', 'max:10'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic', 'max:15360'],
        ], [
            'photos.*.max' => 'Each photograph can be up to 15 MB.',
            'photos.*.mimes' => 'Photographs must be JPG, PNG, WebP or HEIC.',
        ]);

        app(PostProjectUpdate::class)->handle(
            $this->project,
            $this->user(),
            [
                'body' => $this->body,
                'progress_percent' => $this->progressPercent,
                'project_milestone_id' => $this->milestoneId,
            ],
            $this->photos,
            $this->shareWithClient,
        );

        $this->reset(['body', 'photos', 'milestoneId', 'shareWithClient']);
        $this->project->refresh();
        $this->progressPercent = (int) $this->project->progress_percent;

        $this->toast($this->shareWithClient ? 'Update posted and shared with the client.' : 'Update posted.');
    }

    public function share(int $updateId): void
    {
        $this->authorize('update', $this->project);

        $update = $this->project->updates()->findOrFail($updateId);
        app(ShareProjectUpdate::class)->handle($update);

        $this->toast('Shared with the client.');
    }

    public function deleteUpdate(int $updateId): void
    {
        $this->authorize('update', $this->project);

        $this->project->updates()->findOrFail($updateId)->delete();
        $this->toast('Update deleted.');
    }

    public function updatedUploads(): void
    {
        $this->authorize('update', $this->project);

        $this->validate([
            'uploads' => ['array', 'max:10'],
            'uploads.*' => ['file', 'max:20480', 'extensions:'.implode(',', \App\Domain\Crm\Models\Lead::ATTACHMENT_EXTENSIONS)],
        ], [
            'uploads.*.max' => 'Each file can be up to 20 MB.',
            'uploads.*.extensions' => 'Allowed: photos, PDF, DWG/DXF drawings, Excel, Word and PowerPoint files.',
        ]);

        foreach ($this->uploads as $upload) {
            $this->project->addMedia($upload->getRealPath())
                ->usingFileName($upload->hashName())
                ->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
                ->withCustomProperties([
                    'uploaded_by' => $this->user()->id,
                    'original_name' => $upload->getClientOriginalName(),
                ])
                ->toMediaCollection('files');
        }

        $this->uploads = [];
        $this->toast('File uploaded.');
    }

    public function deleteFile(int $mediaId): void
    {
        $this->authorize('update', $this->project);

        $media = $this->project->media()->where('collection_name', 'files')->findOrFail($mediaId);
        assert($media instanceof Media);
        $media->delete();

        $this->toast('File deleted.');
    }

    public function render(): View
    {
        $project = $this->project->load(['contact', 'company', 'quote', 'lead', 'manager', 'invoices']);

        $updates = $project->updates()
            ->with(['author:id,name', 'milestone:id,name', 'media'])
            ->paginate(10, pageName: 'updates');

        return view('livewire.admin.projects.project-show', [
            'project' => $project,
            'milestones' => $project->milestones()->with('quoteMilestone')->get(),
            'updates' => $updates,
            'files' => $project->getMedia('files'),
            'stages' => ProjectStage::cases(),
            'statuses' => ProjectStatus::cases(),
            'staff' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'lakh' => app(SalesSettings::class)->lakh_grouping,
        ])->title($project->code.' · '.$project->name);
    }
}
