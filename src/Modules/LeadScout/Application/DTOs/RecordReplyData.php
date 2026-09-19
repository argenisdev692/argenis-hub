<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Enums\ReplyOutcome;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Reply record (spec FR-42, plan §5 `RecordReplyData`).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class RecordReplyData extends Data
{
    public function __construct(
        public string $outcome,
        public ?string $repliedAt = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'outcome' => ['required', 'string', 'in:'.implode(',', ReplyOutcome::values())],
            'repliedAt' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
