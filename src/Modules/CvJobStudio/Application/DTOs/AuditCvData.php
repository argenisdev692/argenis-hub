<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** CV audit input: which CV (primary when omitted) and an optional target role. */
#[MapInputName(SnakeCaseMapper::class)]
final class AuditCvData extends Data
{
    public function __construct(
        public readonly ?string $cvUuid = null,
        public readonly ?string $targetJobTitle = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'cv_uuid' => ['nullable', 'string', 'uuid'],
            'target_job_title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
