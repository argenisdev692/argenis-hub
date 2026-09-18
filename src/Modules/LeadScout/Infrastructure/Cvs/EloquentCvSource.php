<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Cvs;

use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Modules\LeadScout\Domain\Ports\CvSourcePort;
use Modules\LeadScout\Domain\ValueObjects\CvOption;
use Modules\LeadScout\Domain\ValueObjects\CvSnapshot;

/**
 * Reads the operator's `cvs` rows: own user, not deleted, markdown primary
 * by default. `raw_text` is returned only to the import handler — never to
 * controllers, logs or the IA.
 */
final readonly class EloquentCvSource implements CvSourcePort
{
    public function primaryMarkdownCv(int $userId): ?CvSnapshot
    {
        $cv = CvEloquentModel::query()
            ->where('user_id', $userId)
            ->where('is_primary', true)
            ->where('file_type', CvFileType::Md->value)
            ->latest('updated_at')
            ->first(['uuid', 'file_type', 'raw_text', 'updated_at', 'is_primary', 'title']);

        return $cv === null ? null : self::snapshot($cv);
    }

    public function cvForUser(string $uuid, int $userId): ?CvSnapshot
    {
        $cv = CvEloquentModel::query()
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->first(['uuid', 'file_type', 'raw_text', 'updated_at', 'is_primary', 'title']);

        return $cv === null ? null : self::snapshot($cv);
    }

    public function cvsForUser(int $userId): array
    {
        return CvEloquentModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_primary')
            ->orderByDesc('updated_at')
            ->get(['uuid', 'title', 'niche', 'file_type', 'is_primary', 'updated_at', 'raw_text'])
            ->map(static fn (CvEloquentModel $cv): CvOption => new CvOption(
                uuid: $cv->uuid,
                title: $cv->title,
                niche: $cv->niche->value,
                fileType: $cv->file_type->value,
                isPrimary: $cv->is_primary,
                updatedAt: $cv->updated_at?->toIso8601String() ?? '',
                importable: $cv->file_type === CvFileType::Md && $cv->raw_text !== null && trim($cv->raw_text) !== '',
            ))
            ->all();
    }

    private static function snapshot(CvEloquentModel $cv): CvSnapshot
    {
        return new CvSnapshot(
            uuid: $cv->uuid,
            fileType: $cv->file_type->value,
            rawText: $cv->raw_text,
            contentHash: hash('sha256', (string) $cv->raw_text),
            updatedAt: $cv->updated_at?->toIso8601String() ?? '',
            isPrimary: $cv->is_primary,
            title: $cv->title,
        );
    }
}
