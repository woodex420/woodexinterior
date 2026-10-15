<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Projects\Models\Project;
use App\Domain\Sales\Enums\DiscountType;
use App\Domain\Sales\Enums\QuoteStatus;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Living quotation document. Every send creates an immutable QuoteVersion snapshot (D-15).
 * Totals are written only by App\Domain\Sales\Services\QuoteCalculator (D-14).
 */
class Quote extends Model
{
    use HasPublicId, LogsActivity, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_line' => BusinessLine::class,
            'status' => QuoteStatus::class,
            'discount_type' => DiscountType::class,
            'revision' => 'integer',
            'subtotal_minor' => 'integer',
            'discount_value' => 'integer',
            'discount_minor' => 'integer',
            'other_charges_minor' => 'integer',
            'tax_bp' => 'integer',
            'tax_minor' => 'integer',
            'rounding_minor' => 'integer',
            'total_minor' => 'integer',
            'internal_cost_minor' => 'integer',
            'margin_bp' => 'integer',
            'view_count' => 'integer',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'expired_at' => 'datetime',
            'approved_at' => 'datetime',
            'signatories' => 'array',
            'meta' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sales')
            ->logOnly(['status', 'revision', 'total_minor', 'discount_minor', 'valid_until', 'owner_id', 'approved_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
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
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<QuoteTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(QuoteTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<QuoteSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(QuoteSection::class)->orderBy('position');
    }

    /**
     * @return HasMany<QuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<QuoteMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(QuoteMilestone::class)->orderBy('position');
    }

    /**
     * @return HasMany<QuoteVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(QuoteVersion::class)->orderBy('revision');
    }

    /**
     * @return HasMany<QuoteEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(QuoteEvent::class)->latest('occurred_at');
    }

    /**
     * @return HasOne<Project, $this>
     */
    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    public function total(): Money
    {
        return Money::ofMinor((int) $this->total_minor, (string) $this->currency);
    }

    /**
     * "WI-10123", "WI-10123 R1", "WI-10123 · Option A".
     */
    public function label(): string
    {
        return $this->number
            .($this->revision > 0 ? ' R'.$this->revision : '')
            .($this->option_label !== '' && $this->option_label !== null ? ' · '.$this->option_label : '');
    }
}
