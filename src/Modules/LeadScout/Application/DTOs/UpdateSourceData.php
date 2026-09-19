<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Enums\SourceStatus;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Source tuning (spec US-2): status, frequency and the terms-review stamp.
 * Activating without reviewed terms is rejected in the handler (422) and by
 * the DB CHECK — never trust the client (spec FR-13).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class UpdateSourceData extends Data
{
    public function __construct(
        public ?string $status = null,
        public ?int $frequencyMinutes = null,
        public ?string $termsReviewedAt = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:'.implode(',', [SourceStatus::Active->value, SourceStatus::Paused->value])],
            'frequencyMinutes' => ['nullable', 'integer', 'min:15', 'max:10080'],
            'termsReviewedAt' => ['nullable', 'date'],
        ];
    }
}
