<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\ExtractionMethod;
use Modules\LeadScout\Domain\Enums\SignalDimension;
use Modules\LeadScout\Domain\Enums\SignalNature;

/**
 * Evidence-backed buying signal about a company (spec FR-6, FR-10).
 */
final readonly class Signal
{
    public function __construct(
        public int $id,
        public int $companyId,
        public SignalDimension $dimension,
        public string $signalKey,
        public ?string $valueText,
        public SignalNature $nature,
        public int $confidence,
        public ?string $evidenceUrl,
        public ?string $evidenceExcerpt,
        public ?DateTimeImmutable $capturedAt,
        public ExtractionMethod $extractionMethod,
        public ?string $aiProvider,
        public ?string $aiModel,
    ) {}
}
