<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Studio working hours (Settings → Business). Used for SLA due times (CRM-010) and quiet hours.
 * Pakistan has no daylight saving time, so Asia/Karachi arithmetic is unambiguous.
 */
final readonly class BusinessHours
{
    /**
     * @param  list<int>  $workingDays  ISO day numbers (1 = Monday … 7 = Sunday)
     */
    public function __construct(
        public string $opensAt = '10:00',
        public string $closesAt = '19:00',
        public array $workingDays = [1, 2, 3, 4, 5, 6],
        public string $timezone = 'Asia/Karachi',
    ) {}

    public function isOpen(CarbonImmutable $moment): bool
    {
        $local = $moment->setTimezone($this->timezone);

        if (! in_array($local->dayOfWeekIso, $this->workingDays, true)) {
            return false;
        }

        $time = $local->format('H:i');

        return $time >= $this->opensAt && $time < $this->closesAt;
    }

    /**
     * When must the first response happen? Inside hours: now + $minutes (capped at closing time
     * rolls to the next opening). Outside hours: next opening + $graceMinutes.
     */
    public function dueAt(CarbonImmutable $from, int $minutes = 15, int $graceMinutes = 30): CarbonImmutable
    {
        $local = $from->setTimezone($this->timezone);

        if ($this->isOpen($local)) {
            $due = $local->addMinutes($minutes);

            if ($this->isOpen($due)) {
                return $due;
            }

            return $this->nextOpening($due)->addMinutes($minutes);
        }

        return $this->nextOpening($local)->addMinutes($graceMinutes);
    }

    public function nextOpening(CarbonImmutable $from): CarbonImmutable
    {
        $local = $from->setTimezone($this->timezone);
        [$hour, $minute] = array_map('intval', explode(':', $this->opensAt));

        for ($i = 0; $i <= 8; $i++) {
            $day = $local->startOfDay()->addDays($i);
            $opening = $day->setTime($hour, $minute);

            if (in_array($day->dayOfWeekIso, $this->workingDays, true) && $opening->greaterThan($local)) {
                return $opening;
            }
        }

        return $local->addDay();
    }
}
