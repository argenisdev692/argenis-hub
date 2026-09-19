<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\SuppressionSource;

/**
 * Do-not-contact entry with absolute precedence (spec FR-17, FR-43).
 */
final readonly class Suppression
{
    public function __construct(
        public int $id,
        public string $uuid,
        public ?string $canonicalDomain,
        public ?string $taxId,
        public ?string $name,
        public SuppressionSource $source,
        public ?string $reason,
        public ?string $listPeriod,
        public ?DateTimeImmutable $createdAt,
    ) {}
}
