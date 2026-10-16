<?php

declare(strict_types=1);

namespace App\Domain\Projects\Models;

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Models\Concerns\HasAttachments;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Sales\Enums\InvoiceStatus;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Quote;
use App\Domain\Website\Models\Page;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Project created from an accepted quotation (PRJ-001). Code = quotation number.
 */
class Project extends Model implements HasMedia
{
    use HasAttachments, HasPublicId, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_line' => BusinessLine::class,
            'stage' => ProjectStage::class,
            'status' => ProjectStatus::class,
            'progress_percent' => 'integer',
            'start_date' => 'date',
            'target_date' => 'date',
            'completed_at' => 'datetime',
            'handover_started_at' => 'datetime',
            'handover_completed_at' => 'datetime',
            'client_ok_at' => 'datetime',
            'review_requested_at' => 'datetime',
            'case_study_consent' => 'boolean',
            'value_minor' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_id');
    }

    /**
     * @return HasMany<ProjectMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('position');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<ProjectUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    /**
     * @return HasMany<ProjectHandoverItem, $this>
     */
    public function handoverItems(): HasMany
    {
        return $this->hasMany(ProjectHandoverItem::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function caseStudyPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'case_study_page_id');
    }

    /**
     * The diary as the client may read it: only what was shared, newest first.
     *
     * @return EloquentCollection<int, ProjectUpdate>
     */
    public function clientTimeline(): EloquentCollection
    {
        $this->loadMissing('updates.author', 'updates.milestone');

        return $this->updates->where('client_visible', true)->values();
    }

    /**
     * Drawings and documents the team chose to show the client (the rest stay in the studio).
     *
     * @return \Illuminate\Support\Collection<int, Media>
     */
    public function clientDocuments(): \Illuminate\Support\Collection
    {
        return $this->getMedia('files')
            ->filter(fn (\Spatie\MediaLibrary\MediaCollections\Models\Media $file): bool => (bool) $file->getCustomProperty('visible_to_client'))
            ->values();
    }

    /**
     * How much of the handover checklist is ticked.
     *
     * @return array{0: int, 1: int} done, total
     */
    public function handoverProgress(): array
    {
        $this->loadMissing('handoverItems');

        return [(int) $this->handoverItems->filter(fn (ProjectHandoverItem $item): bool => $item->isDone())->count(), $this->handoverItems->count()];
    }

    /** The handover is finished when every line is ticked and the client has signed for it. */
    public function isHandedOver(): bool
    {
        [$done, $total] = $this->handoverProgress();

        return $total > 0 && $done === $total && $this->client_ok_at !== null;
    }

    /** Invoiced so far: every invoice except the voided ones (PRJ-005). */
    public function invoicedMinor(): int
    {
        $this->loadMissing('invoices');

        return (int) $this->invoices
            ->reject(fn (Invoice $invoice): bool => $invoice->status === InvoiceStatus::Void)
            ->sum('total_minor');
    }

    /** Money actually received against this project's invoices. */
    public function collectedMinor(): int
    {
        $this->loadMissing('invoices');

        return (int) $this->invoices->sum('paid_minor');
    }

    /** Still owed on this project: invoiced, not void, not yet collected. */
    public function balanceMinor(): int
    {
        $this->loadMissing('invoices');

        return (int) $this->invoices
            ->reject(fn (Invoice $invoice): bool => $invoice->status === InvoiceStatus::Void)
            ->sum('balance_minor');
    }

    /** The next milestone still ahead of us: the one the team is working towards. */
    public function nextMilestone(): ?ProjectMilestone
    {
        $this->loadMissing('milestones');

        return $this->milestones->firstWhere('reached_at', null);
    }

    /** A project is late when its target date has passed and it is not finished. */
    public function isOverdue(): bool
    {
        return $this->target_date !== null
            && $this->target_date->lt(today())
            && ! in_array($this->status, [ProjectStatus::Completed, ProjectStatus::Cancelled], true);
    }

    public function value(): Money
    {
        return Money::ofMinor((int) $this->value_minor);
    }
}
