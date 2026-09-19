<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Manual lead intake (spec FR-20): company name + URL + operator note.
 * Country is unknown at this point — enrichment backfills it.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class CreateManualLeadData extends Data
{
    public function __construct(
        public string $name,
        public string $url,
        public ?string $note = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048', 'url', 'starts_with:http://,https://'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
