<?php

declare(strict_types=1);

namespace Modules\Backups\Application\DTOs;

use Modules\Backups\Domain\Support\HumanBytes;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Admin-facing representation of one backup archive (or failed attempt). The
 * allowlist behind every `/data/admin/backups` response: the auto-increment
 * `id` never crosses this boundary (OWASP §12).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class BackupData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $disk,
        public readonly ?string $path,
        public readonly string $filename,
        public readonly ?int $sizeBytes,
        public readonly string $humanSize,
        public readonly string $status,
        public readonly ?string $connection,
        public readonly ?string $error,
        public readonly ?string $startedAt,
        public readonly ?string $finishedAt,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromModel(BackupEloquentModel $backup): self
    {
        return new self(
            uuid: $backup->uuid,
            disk: $backup->disk,
            path: $backup->path,
            filename: $backup->filename,
            sizeBytes: $backup->size_bytes,
            humanSize: HumanBytes::format($backup->size_bytes),
            status: $backup->status->value,
            connection: $backup->connection,
            error: $backup->error,
            startedAt: $backup->started_at?->toIso8601String(),
            finishedAt: $backup->finished_at?->toIso8601String(),
            createdAt: $backup->created_at?->toIso8601String(),
            updatedAt: $backup->updated_at?->toIso8601String(),
        );
    }
}
