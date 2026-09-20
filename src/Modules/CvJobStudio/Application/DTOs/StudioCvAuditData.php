<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** One recruiter-scan audit result (Audit page) — allowlist, no internal ids (OWASP §12). */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioCvAuditData extends Data
{
    /**
     * @param  list<string>|null  $verdictReasons
     * @param  list<string>|null  $strengths
     * @param  list<string>|null  $improvements
     * @param  list<string>|null  $keywordGaps
     * @param  list<string>|null  $xyzGaps
     */
    public function __construct(
        public readonly string $uuid,
        public readonly ?string $verdict,
        public readonly ?array $verdictReasons,
        public readonly ?string $targetJobTitle,
        public readonly ?array $strengths,
        public readonly ?array $improvements,
        public readonly ?array $keywordGaps,
        public readonly ?array $xyzGaps,
        public readonly ?string $createdAt,
    ) {}
}
