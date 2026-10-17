<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\PeriodStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A month of books. Posting is refused into a closed period (D-118): corrections are reversals in the next open
 * month, so a signed month can never change under the accountant's feet.
 */
class AccountingPeriod extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => PeriodStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'period_id');
    }

    public function isOpen(): bool
    {
        return $this->status === PeriodStatus::Open;
    }

    /** The month that contains a date, created open on first use. */
    public static function forDate(CarbonImmutable|\DateTimeInterface|string $date): self
    {
        $day = CarbonImmutable::parse($date);
        $start = $day->startOfMonth();
        $end = $day->endOfMonth();

        /** @var self|null $period */
        $period = self::query()
            ->whereDate('starts_on', $start->toDateString())
            ->whereDate('ends_on', $end->toDateString())
            ->first();

        if ($period === null) {
            $period = self::query()->create([
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'name' => $start->format('F Y'),
                'status' => PeriodStatus::Open,
            ]);
        }

        return $period;
    }

    public function label(): string
    {
        return $this->name;
    }
}
