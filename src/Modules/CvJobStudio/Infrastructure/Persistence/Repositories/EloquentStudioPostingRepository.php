<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Application\DTOs\StudioPostingFilterData;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Services\PostingFingerprint;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioGateResultEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingSourceEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingTextEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRequirementEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillRelationEloquentModel;

/**
 * Deletes and restores go through the model instance, never a mass query:
 * mass soft deletes skip the `LogsActivity` audit trail (same reason as
 * the Cvs repository).
 */
final readonly class EloquentStudioPostingRepository implements StudioPostingRepositoryPort
{
    /** @var list<string> */
    private const array LIST_COLUMNS = [
        'id',
        'uuid',
        'user_id',
        'profile_id',
        'employer_name',
        'title',
        'location_text',
        'remote_scope',
        'posted_at',
        'status',
        'discovery_channel',
        'apply_destination',
        'd_disc',
        'is_expired',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function paginate(StudioPostingFilterData $filters, int $perPage, int $userId): LengthAwarePaginator
    {
        return StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->applyFilters($filters)
            // Only the newest score per posting (the relation orders newest
            // first) — `StudioPostingData::fromModel()` reads `scores->first()`
            // for the Fit column.
            ->with(['scores' => static fn ($scores) => $scores
                ->select(['id', 'posting_id', 'total_score', 'band', 'cap_reason'])
                ->limit(1)])
            ->select(self::LIST_COLUMNS)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUuidForUser(string $uuid, int $userId): ?StudioPostingEloquentModel
    {
        return StudioPostingEloquentModel::withTrashed()
            ->ownedBy($userId)
            ->with(['profile:uuid,name,slug', 'requirements', 'gateResults', 'texts', 'scores'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function create(array $attributes): StudioPostingEloquentModel
    {
        return DB::transaction(
            static fn (): StudioPostingEloquentModel => StudioPostingEloquentModel::query()->create($attributes),
        );
    }

    public function update(StudioPostingEloquentModel $posting, array $attributes): StudioPostingEloquentModel
    {
        return DB::transaction(static function () use ($posting, $attributes): StudioPostingEloquentModel {
            $posting->update($attributes);

            return $posting->refresh();
        });
    }

    public function softDelete(string $uuid, int $userId): bool
    {
        return DB::transaction(static fn (): bool => (bool) StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first(self::LIST_COLUMNS)
            ?->delete());
    }

    public function restore(string $uuid, int $userId): bool
    {
        return DB::transaction(static fn (): bool => (bool) StudioPostingEloquentModel::onlyTrashed()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first(self::LIST_COLUMNS)
            ?->restore());
    }

    public function bulkSoftDeleteForUser(array $uuids, int $userId): int
    {
        if ($uuids === []) {
            return 0;
        }

        return DB::transaction(static fn (): int => StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->whereIn('uuid', $uuids)
            ->lockForUpdate()
            ->get(self::LIST_COLUMNS)
            ->filter(static fn (StudioPostingEloquentModel $posting): bool => (bool) $posting->delete())
            ->count());
    }

    public function bulkRestoreForUser(array $uuids, int $userId): int
    {
        if ($uuids === []) {
            return 0;
        }

        return DB::transaction(static fn (): int => StudioPostingEloquentModel::onlyTrashed()
            ->ownedBy($userId)
            ->whereIn('uuid', $uuids)
            ->lockForUpdate()
            ->get(self::LIST_COLUMNS)
            ->filter(static fn (StudioPostingEloquentModel $posting): bool => $posting->restore())
            ->count());
    }

    public function ingest(array $posting, ?string $text, array $requirements, array $verdicts): StudioPostingEloquentModel
    {
        return DB::transaction(static function () use ($posting, $text, $requirements, $verdicts): StudioPostingEloquentModel {
            // withTrashed: a soft-deleted posting still owns its URL (unique
            // index). Re-discovery refreshes it but never resurrects it — the
            // user deleted it on purpose; restore is an explicit action.
            $model = StudioPostingEloquentModel::withTrashed()
                ->ownedBy($posting['user_id'])
                ->where('url_hash', $posting['url_hash'])
                ->lockForUpdate()
                ->first();

            if ($model === null) {
                $model = StudioPostingEloquentModel::query()->create([
                    ...$posting,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                ]);
            } else {
                $model->update(['last_seen_at' => now()]);
            }

            if ($text !== null && trim($text) !== '') {
                StudioPostingTextEloquentModel::query()->create([
                    'user_id' => $posting['user_id'],
                    'posting_id' => $model->id,
                    'ladder_step' => 'manual',
                    'completeness' => 'full',
                    'text' => $text,
                    'char_count' => mb_strlen($text),
                    'fetched_at' => now(),
                ]);
            }

            foreach ($requirements as $requirement) {
                StudioRequirementEloquentModel::query()->updateOrCreate(
                    ['posting_id' => $model->id, 'canonical_name' => $requirement['canonical_name']],
                    [
                        'user_id' => $posting['user_id'],
                        'tag' => $requirement['tag'],
                        'nature' => $requirement['nature'],
                    ],
                );
            }

            foreach ($verdicts as $verdict) {
                StudioGateResultEloquentModel::query()->updateOrCreate(
                    ['posting_id' => $model->id, 'gate_code' => $verdict->gate->value],
                    [
                        'user_id' => $posting['user_id'],
                        'passed' => $verdict->passed,
                        'reason_code' => $verdict->reasonCode,
                        'detail' => $verdict->detail,
                    ],
                );
            }

            return $model->refresh();
        });
    }

    public function scoringContext(string $uuid, int $userId): ?StudioPostingEloquentModel
    {
        return StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->with(['profile', 'requirements', 'gateResults', 'texts'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function confirmedRelations(int $userId): array
    {
        return StudioSkillRelationEloquentModel::query()
            ->where('user_id', $userId)
            ->where('status', 'confirmed')
            ->get(['from_skill', 'to_skill', 'kind'])
            ->map(static fn ($relation): array => [
                'from' => $relation->from_skill,
                'to' => $relation->to_skill,
                'kind' => $relation->kind,
            ])
            ->all();
    }

    public function setStatus(string $uuid, int $userId, string $status): bool
    {
        return (bool) StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->update(['status' => $status]);
    }

    public function paginateReferences(int $userId, int $perPage): LengthAwarePaginator
    {
        return StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->where('status', 'reference')
            ->select(['id', 'uuid', 'user_id', 'title', 'employer_name', 'canonical_url', 'source', 'created_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function deduplicate(int $userId): int
    {
        return DB::transaction(static function () use ($userId): int {
            $merged = 0;

            StudioPostingEloquentModel::query()
                ->ownedBy($userId)
                ->whereNull('fingerprint')
                ->chunkById(200, static function ($postings) use ($userId, &$merged): void {
                    foreach ($postings as $posting) {
                        $fingerprint = PostingFingerprint::make(
                            (string) $posting->employer_name,
                            $posting->title,
                            $posting->location_text,
                            $posting->posted_at?->format('Y-m-d'),
                        );

                        $canonical = StudioPostingEloquentModel::query()
                            ->ownedBy($userId)
                            ->where('fingerprint', $fingerprint)
                            ->where('id', '!=', $posting->id)
                            ->lockForUpdate()
                            ->first();

                        if ($canonical === null) {
                            $posting->update(['fingerprint' => $fingerprint]);

                            continue;
                        }

                        $sourceIds = StudioPostingSourceEloquentModel::query()
                            ->where('user_id', $userId)
                            ->where('posting_id', $posting->id)
                            ->pluck('source_id')
                            ->all();

                        foreach ($sourceIds as $sourceId) {
                            $alreadyAttached = StudioPostingSourceEloquentModel::query()
                                ->where('user_id', $userId)
                                ->where('posting_id', $canonical->id)
                                ->where('source_id', $sourceId)
                                ->exists();

                            if ($alreadyAttached) {
                                StudioPostingSourceEloquentModel::query()
                                    ->where('user_id', $userId)
                                    ->where('posting_id', $posting->id)
                                    ->where('source_id', $sourceId)
                                    ->delete();
                            } else {
                                StudioPostingSourceEloquentModel::query()
                                    ->where('user_id', $userId)
                                    ->where('posting_id', $posting->id)
                                    ->where('source_id', $sourceId)
                                    ->update(['posting_id' => $canonical->id]);
                            }
                        }

                        $posting->delete();
                        $merged++;
                    }
                });

            return $merged;
        });
    }
}
