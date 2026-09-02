<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\DTOs;

use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Domain\Enums\CvNiche;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The allowlist behind every CV response — Inertia props and JSON alike.
 *
 * Three columns deliberately never cross this boundary (OWASP §12): the
 * auto-increment `id`, the owning `user_id`, and `raw_text` — the full extracted
 * résumé, which is the most sensitive PII the module holds. `file_path` is the
 * raw R2 object key and is exposed only as a short-lived signed `downloadUrl`,
 * populated on the detail endpoint and left null in list responses.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CvData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $title,
        public readonly CvNiche $niche,
        public readonly bool $isPrimary,
        public readonly CvFileType $fileType,
        public readonly string $originalFilename,
        public readonly ?string $ownerName,
        public readonly ?string $downloadUrl,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $deletedAt,
    ) {}

    public static function fromModel(CvEloquentModel $cv, ?string $downloadUrl = null): self
    {
        return new self(
            uuid: $cv->uuid,
            title: $cv->title,
            niche: $cv->niche,
            isPrimary: $cv->is_primary,
            fileType: $cv->file_type,
            originalFilename: $cv->original_filename,
            ownerName: self::ownerName($cv),
            downloadUrl: $downloadUrl,
            createdAt: $cv->created_at?->toIso8601String(),
            updatedAt: $cv->updated_at?->toIso8601String(),
            deletedAt: $cv->deleted_at?->toIso8601String(),
        );
    }

    /**
     * Owner label, only when the relation was explicitly eager-loaded — reading
     * it otherwise would be the N+1 this module's `with('user:id,…')` avoids.
     */
    private static function ownerName(CvEloquentModel $cv): ?string
    {
        if (! $cv->relationLoaded('user')) {
            return null;
        }

        $name = trim(sprintf('%s %s', $cv->user->first_name, $cv->user->last_name ?? ''));

        return $name !== '' ? $name : null;
    }
}
