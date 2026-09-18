<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\ValueObjects\CvOption;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * CV picker row: chooser fields only, never `raw_text` (spec US-1 CA-1).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CvOptionData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $title,
        public readonly string $niche,
        public readonly string $fileType,
        public readonly bool $isPrimary,
        public readonly string $updatedAt,
        public readonly bool $importable,
    ) {}

    public static function fromOption(CvOption $option): self
    {
        return new self(
            uuid: $option->uuid,
            title: $option->title,
            niche: $option->niche,
            fileType: $option->fileType,
            isPrimary: $option->isPrimary,
            updatedAt: $option->updatedAt,
            importable: $option->importable,
        );
    }
}
