<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\ExtractionMethod;
use Modules\LeadScout\Domain\Enums\SignalDimension;
use Modules\LeadScout\Domain\Enums\SignalNature;

/**
 * A signal about to be stored.
 */
final readonly class NewSignal
{
    public function __construct(
        public SignalDimension $dimension,
        public string $signalKey,
        public SignalNature $nature,
        public int $confidence,
        public ExtractionMethod $extractionMethod,
        public DateTimeImmutable $capturedAt,
        public ?string $valueText = null,
        public ?string $evidenceUrl = null,
        public ?string $evidenceExcerpt = null,
        public ?string $aiProvider = null,
        public ?string $aiModel = null,
    ) {}
}
