<?php

declare(strict_types=1);

namespace Modules\Company\Domain\Events;

use DateTimeImmutable;

/**
 * The singleton company record moved to a different country.
 *
 * Emitted by `UpdateCompanyHandler` (no import: Domain never references the
 * Application layer, not even from a docblock) only when `country_code`
 * actually changes — a re-save with the same value is not a relocation and must
 * not trigger downstream rebuilds.
 *
 * The Company module knows nothing about its consumers; each subscribing module
 * registers its own listener (Availability rebuilds the national holidays it
 * materialised for the previous country).
 */
final readonly class CompanyCountryChanged
{
    public function __construct(
        public ?string $previousCountryCode,
        public ?string $currentCountryCode,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}
}
