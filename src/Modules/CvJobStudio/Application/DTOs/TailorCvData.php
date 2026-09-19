<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Agent-chat tailoring input: output language plus the operator's free-text
 * notes (extra skills, details to emphasize). Notes travel to the agent as a
 * delimited, advisory-only block — the agent must still ground every skill in
 * the source CV (OWASP LLM01, FR-22).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class TailorCvData extends Data
{
    public function __construct(
        public readonly string $language = 'en',
        public readonly ?string $notes = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'language' => ['sometimes', 'string', 'in:es,en,pt-PT'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
