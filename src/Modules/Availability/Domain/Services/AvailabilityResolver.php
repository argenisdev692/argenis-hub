<?php

declare(strict_types=1);

namespace Modules\Availability\Domain\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\Availability\Domain\Ports\AvailabilityExceptionRepositoryPort;
use Modules\Availability\Domain\Ports\AvailabilityRuleRepositoryPort;
use Modules\Availability\Domain\ValueObjects\DateException;
use Modules\Availability\Domain\ValueObjects\ResolvedDay;
use Modules\Availability\Domain\ValueObjects\TimeSlot;

final readonly class AvailabilityResolver
{
    public function __construct(
        private AvailabilityRuleRepositoryPort $rules,
        private AvailabilityExceptionRepositoryPort $exceptions,
    ) {}

    public function resolve(CarbonInterface $date): ResolvedDay
    {
        $key = $date->format('Y-m-d');

        return $this->buildDay(
            $key,
            $this->exceptions->findActiveByDate($key),
            $this->rules->availableSlotsForDay((int) $date->dayOfWeek),
        );
    }

    /**
     * @return array<string, ResolvedDay>
     */
    public function resolveRange(CarbonInterface $from, CarbonInterface $to): array
    {
        $cursor = CarbonImmutable::parse($from->format('Y-m-d'));
        $end = CarbonImmutable::parse($to->format('Y-m-d'));

        // Two queries for the whole window (the active exceptions in range, keyed
        // by date, plus the open slots grouped by weekday) instead of two per
        // day — the loop below then resolves each day purely in memory.
        $exceptions = $this->exceptions->activeBetween($cursor->toDateString(), $end->toDateString());
        $slotsByWeekday = $this->rules->availableSlotsGroupedByDay();

        $days = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m-d');
            $days[$key] = $this->buildDay(
                $key,
                $exceptions[$key] ?? null,
                $slotsByWeekday[(int) $cursor->dayOfWeek] ?? [],
            );
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * Applies the precedence chain for a single date: an active exception wins
     * over the weekly rule, and a day with no open slots is closed by default.
     *
     * @param  list<TimeSlot>  $slots
     */
    private function buildDay(string $key, ?DateException $exception, array $slots): ResolvedDay
    {
        if ($exception !== null) {
            if (! $exception->isAvailable) {
                return ResolvedDay::closed($key, 'exception', $exception->reason);
            }

            return ResolvedDay::open(
                $key,
                [new TimeSlot((string) $exception->startTime, (string) $exception->endTime)],
                'exception',
                $exception->reason,
            );
        }

        if ($slots === []) {
            return ResolvedDay::closed($key, 'default');
        }

        return ResolvedDay::open($key, $slots, 'rule');
    }
}
