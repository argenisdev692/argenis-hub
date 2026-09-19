<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

use DateTimeImmutable;

/**
 * Inclusive reporting window; an open bound means "no limit".
 */
final readonly class MetricsPeriod
{
    public function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
    ) {}
}
