<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Response allowlist for postings — Inertia props and JSON alike (OWASP §12).
 * Full posting text and raw requirement rows never cross this boundary; the
 * detail endpoint exposes counts and the latest score instead.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioPostingData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $title,
        public readonly ?string $employerName,
        public readonly ?string $locationText,
        public readonly ?string $remoteScope,
        public readonly string $status,
        public readonly ?string $discoveryChannel,
        public readonly ?string $applyDestination,
        public readonly ?float $totalScore,
        public readonly ?string $band,
        public readonly ?string $capReason,
        public readonly ?string $attribution,
        public readonly ?string $canonicalUrl,
        public readonly ?string $createdAt,
        public readonly ?string $deletedAt,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'canonical_url' => ['required', 'string', 'max:2048', 'url'],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'profile_uuid' => ['required', 'string', 'uuid'],
            'text' => ['nullable', 'string', 'max:100000'],
        ];
    }
}
