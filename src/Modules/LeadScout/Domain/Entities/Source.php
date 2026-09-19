<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\SourceStatus;
use Modules\LeadScout\Domain\Enums\SourceType;

/**
 * Job-offer source (spec FR-2). Never active without reviewed terms (FR-13).
 */
final readonly class Source
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $name,
        public SourceType $type,
        public ?string $country,
        public string $accessMethod,
        public int $frequencyMinutes,
        public int $priority,
        public SourceStatus $status,
        public ?DateTimeImmutable $lastRunAt,
        public ?string $lastCursor,
        public ?DateTimeImmutable $termsReviewedAt,
    ) {}
}
