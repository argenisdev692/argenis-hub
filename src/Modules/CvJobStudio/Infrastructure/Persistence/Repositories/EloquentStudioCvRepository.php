<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Application\DTOs\StudioCvAuditData;
use Modules\CvJobStudio\Application\DTOs\StudioCvVersionData;
use Modules\CvJobStudio\Domain\Ports\StudioCvRepositoryPort;
use Modules\CvJobStudio\Domain\ValueObjects\CvStructureSnapshot;
use Modules\CvJobStudio\Domain\ValueObjects\CvVersionSnapshot;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvAuditEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvBulletEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvEntryEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvSkillEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvStructureEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioExportEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioMetricAnswerEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Mappers\StudioReadModelMapper;

final readonly class EloquentStudioCvRepository implements StudioCvRepositoryPort
{
    public function storeStructure(int $userId, int $cvId, string $sourceTextHash, array $parsed, array $projects): array
    {
        return DB::transaction(static function () use ($userId, $cvId, $sourceTextHash, $parsed, $projects): array {
            $structure = StudioCvStructureEloquentModel::query()->create([
                'user_id' => $userId,
                'cv_id' => $cvId,
                'source_text_hash' => $sourceTextHash,
                'parser_version' => $parsed['parser_version'],
                'parsed_at' => now(),
                'profile_facts' => $parsed['profile_facts'],
            ]);

            $entryIds = [];

            foreach ($parsed['entries'] as $ordinal => $entry) {
                $entryIds[] = StudioCvEntryEloquentModel::query()->create([
                    'user_id' => $userId,
                    'structure_id' => $structure->id,
                    'kind' => $entry['kind'] ?? 'experience',
                    'ordinal' => $ordinal,
                    'organization' => $entry['organization'] ?? null,
                    'role_title' => $entry['role_title'] ?? null,
                    'location' => $entry['location'] ?? null,
                    'is_current' => (bool) ($entry['is_current'] ?? false),
                ])->id;
            }

            $bullets = [];

            foreach ($parsed['bullets'] as $ordinal => $bullet) {
                $text = (string) ($bullet['text'] ?? '');

                $created = StudioCvBulletEloquentModel::query()->create([
                    'user_id' => $userId,
                    'structure_id' => $structure->id,
                    'entry_id' => $entryIds[$bullet['entry_ordinal'] ?? 0] ?? $entryIds[0] ?? 0,
                    'ordinal' => $ordinal,
                    'text' => $text,
                    'has_metric' => (bool) ($bullet['has_metric'] ?? false),
                    'xyz_complete' => (bool) ($bullet['xyz_complete'] ?? false),
                    'char_count' => mb_strlen($text),
                ]);

                $bullets[] = ['id' => $created->id, 'text' => $text];
            }

            foreach ($parsed['skills'] as $skill) {
                StudioCvSkillEloquentModel::query()->create([
                    'user_id' => $userId,
                    'structure_id' => $structure->id,
                    'canonical_name' => $skill['canonical_name'] ?? 'unknown',
                    'raw_name' => $skill['raw_name'] ?? null,
                    'nature' => $skill['nature'] ?? 'hard',
                    'evidence' => $skill['evidence'] ?? 'list_only',
                ]);
            }

            foreach ($projects as $ordinal => $project) {
                StudioCvEntryEloquentModel::query()->create([
                    'user_id' => $userId,
                    'structure_id' => $structure->id,
                    'kind' => 'project',
                    'ordinal' => count($entryIds) + $ordinal,
                    'organization' => $project['title'],
                    'role_title' => $project['url'],
                ]);
            }

            return ['uuid' => $structure->uuid, 'bullets' => $bullets];
        });
    }

    /** Instance update so the confirmation reaches the activity log (a mass update would skip it). */
    public function confirmStructure(string $uuid, int $userId): bool
    {
        return DB::transaction(static fn (): bool => (bool) StudioCvStructureEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first()
            ?->update(['confirmed_at' => now()]));
    }

    public function structureForUser(string $uuid, int $userId): ?CvStructureSnapshot
    {
        $structure = StudioCvStructureEloquentModel::query()
            ->ownedBy($userId)
            ->with(['entries.bullets', 'skills'])
            ->where('uuid', $uuid)
            ->first();

        return $structure !== null ? StudioReadModelMapper::cvStructureSnapshot($structure) : null;
    }

    public function bulletsForStructure(int $structureId, int $userId): array
    {
        return StudioCvBulletEloquentModel::query()
            ->where('user_id', $userId)
            ->where('structure_id', $structureId)
            ->toBase()
            ->pluck('text', 'id')
            ->map(static fn (mixed $text): string => (string) $text)
            ->all();
    }

    public function createVersion(array $attributes): StudioCvVersionData
    {
        return DB::transaction(static fn (): StudioCvVersionData => StudioReadModelMapper::cvVersion(
            StudioCvVersionEloquentModel::query()->create($attributes),
        ));
    }

    public function versionForUser(string $uuid, int $userId): ?CvVersionSnapshot
    {
        $version = StudioCvVersionEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->first(['id', 'uuid', 'cv_id', 'structure_id', 'profile_id', 'posting_id', 'purpose', 'language', 'content']);

        return $version !== null ? StudioReadModelMapper::cvVersionSnapshot($version) : null;
    }

    public function paginateVersions(int $userId, int $perPage): LengthAwarePaginator
    {
        return StudioCvVersionEloquentModel::query()
            ->ownedBy($userId)
            ->select(['id', 'uuid', 'purpose', 'language', 'created_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100))
            ->through(StudioReadModelMapper::cvVersion(...));
    }

    public function createAudit(array $attributes): StudioCvAuditData
    {
        return DB::transaction(static fn (): StudioCvAuditData => StudioReadModelMapper::cvAudit(
            StudioCvAuditEloquentModel::query()->create($attributes),
        ));
    }

    public function auditRefForUser(string $uuid, int $userId): ?array
    {
        $audit = StudioCvAuditEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->first(['id', 'cv_id']);

        return $audit !== null ? ['id' => $audit->id, 'cv_id' => (int) $audit->cv_id] : null;
    }

    public function saveMetricAnswers(int $auditId, int $cvId, int $userId, array $answers): int
    {
        return DB::transaction(static function () use ($auditId, $cvId, $userId, $answers): int {
            foreach ($answers as $question => $answer) {
                StudioMetricAnswerEloquentModel::query()->updateOrCreate(
                    ['audit_id' => $auditId, 'question' => $question],
                    ['user_id' => $userId, 'cv_id' => $cvId, 'answer' => $answer, 'answered_at' => now()],
                );
            }

            return count($answers);
        });
    }

    public function recordExport(array $attributes): array
    {
        $export = StudioExportEloquentModel::query()->create($attributes);

        return [
            'uuid' => $export->uuid,
            'path' => (string) $export->path,
            'verified' => (bool) $export->text_extraction_verified,
        ];
    }
}
