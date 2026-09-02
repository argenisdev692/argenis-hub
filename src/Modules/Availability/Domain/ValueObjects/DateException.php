<?php

declare(strict_types=1);

namespace Modules\Availability\Domain\ValueObjects;

/**
 * A domain-typed projection of an active date exception, produced by the
 * repository so the AvailabilityResolver (a pure domain service) never touches
 * Eloquent. Times are normalised to `H:i`; a closure carries null hours.
 */
final readonly class DateException
{
    public function __construct(
        public string $date,
        public bool $isAvailable,
        public ?string $startTime,
        public ?string $endTime,
        public ?string $reason,
    ) {}
}
