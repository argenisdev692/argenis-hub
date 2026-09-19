<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\ContractType;
use Modules\LeadScout\Domain\Enums\PostingStatus;
use Modules\LeadScout\Domain\Enums\RemoteMode;

/**
 * Deduplicated job offer — a buying signal, never the target itself
 * (spec US-2, FR-4).
 */
final readonly class JobPosting
{
    public function __construct(
        public int $id,
        public string $uuid,
        public ?int $companyId,
        public string $fingerprint,
        public ?string $companyName,
        public string $title,
        public ?string $location,
        public ?string $country,
        public RemoteMode $remoteMode,
        public ContractType $contractType,
        public ?string $language,
        public ?DateTimeImmutable $publishedAt,
        public PostingStatus $status,
        public string $sourceUrl,
        public ?string $companyUrl,
        public ?string $bodyText,
    ) {}
}
