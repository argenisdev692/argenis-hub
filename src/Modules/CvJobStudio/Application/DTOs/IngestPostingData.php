<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Manual posting intake (references + pasted text live here until the
 * discovery providers land). Requirements arrive structured — the LLM
 * extractor is a later slice; this DTO is its future output contract.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class IngestPostingData extends Data
{
    /**
     * @param  list<array{canonical_name: string, tag: string, nature: string}>  $requirements
     */
    public function __construct(
        public readonly string $profileUuid,
        public readonly string $title,
        public readonly string $canonicalUrl,
        public readonly ?string $employerName = null,
        public readonly ?string $locationText = null,
        public readonly ?string $source = null,
        public readonly ?string $discoveryChannel = null,
        public readonly ?string $text = null,
        public readonly array $requirements = [],
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'profile_uuid' => ['required', 'string', 'uuid'],
            'title' => ['required', 'string', 'max:500'],
            'canonical_url' => ['required', 'string', 'max:2048', 'url'],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:64'],
            'discovery_channel' => ['nullable', 'string', 'max:32'],
            'text' => ['nullable', 'string', 'max:100000'],
            'requirements' => ['nullable', 'array', 'max:100'],
            'requirements.*.canonical_name' => ['required_with:requirements', 'string', 'max:255'],
            'requirements.*.tag' => ['required_with:requirements', 'string', 'in:required,preferred,bonus'],
            'requirements.*.nature' => ['required_with:requirements', 'string', 'in:hard,soft'],
        ];
    }
}
