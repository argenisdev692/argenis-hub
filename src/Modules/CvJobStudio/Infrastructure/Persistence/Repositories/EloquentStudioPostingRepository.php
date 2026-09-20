<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Application\DTOs\StudioPostingDetailData;
use Modules\CvJobStudio\Application\DTOs\StudioPostingFilterData;
use Modules\CvJobStudio\Application\DTOs\StudioReferenceData;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Services\PostingFingerprint;
use Modules\CvJobStudio\Domain\ValueObjects\PostingScoringContext;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioGateResultEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingSourceEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingTextEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRequirementEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillRelationEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Mappers\StudioReadModelMapper;

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
        'attribution',
        'canonical_url',
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
            // first) — the mapper reads `scores->first()` for the Fit column.
            ->with(['scores' => static fn ($scores) => $scores
                ->select(['id', 'posting_id', 'total_score', 'band', 'cap_reason'])
                ->limit(1)])
            ->select(self::LIST_COLUMNS)
            ->paginate($perPage)
            ->withQueryString()
            ->through(StudioReadModelMapper::posting(...));
    }

    public function detailForUser(string $uuid, int $userId): ?StudioPostingDetailData
    {
        $posting = StudioPostingEloquentModel::withTrashed()
            ->ownedBy($userId)
            // Exactly what the detail view reads (BACKEND-PHP §4.1): full JD
            // text never loads here, and only the newest score is needed.
            ->with([
                'requirements:id,uuid,posting_id,canonical_name,tag,nature',
                'gateResults:id,posting_id,gate_code,passed,reason_code',
                'scores' => static fn ($scores) => $scores
                    ->select(['id', 'uuid', 'posting_id', 'h', 's', 'd', 'raw_score', 'total_score', 'band', 'cap_reason', 'rules_version', 'computed_at'])
                    ->limit(1),
            ])
            ->where('uuid', $uuid)
            ->first(self::LIST_COLUMNS);

        return $posting !== null ? StudioReadModelMapper::postingDetail($posting) : null;
    }

    public function idForUser(string $uuid, int $userId): ?int
    {
        $id = StudioPostingEloquentModel::withTrashed()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->value('id');

        return $id !== null ? (int) $id : null;
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

    public function ingest(array $posting, ?string $text, array $requirements, array $verdicts): int
    {
        return DB::transaction(static function () use ($posting, $text, $requirements, $verdicts): int {
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

            return $model->id;
        });
    }

    public function scoringContext(string $uuid, int $userId): ?PostingScoringContext
    {
        $posting = StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->with([
                'profile:id,rules,rules_version',
                'requirements:id,uuid,posting_id,canonical_name,raw_text,tag,nature',
                'gateResults:id,posting_id,gate_code,passed',
                // Only the newest JD text is ever read.
                'texts' => static fn ($texts) => $texts->select(['id', 'posting_id', 'text'])->latest('id')->limit(1),
            ])
            ->where('uuid', $uuid)
            ->first(['id', 'uuid', 'user_id', 'profile_id']);

        return $posting !== null ? StudioReadModelMapper::scoringContext($posting) : null;
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

    /** Instance update, not a mass query: `status` is in the activity-log allowlist. */
    public function setStatus(string $uuid, int $userId, string $status): bool
    {
        return DB::transaction(static fn (): bool => (bool) StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first(self::LIST_COLUMNS)
            ?->update(['status' => $status]));
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
            ->withQueryString()
            ->through(static fn (StudioPostingEloquentModel $posting): StudioReferenceData => new StudioReferenceData(
                uuid: $posting->uuid,
                title: $posting->title,
                employerName: $posting->employer_name,
                canonicalUrl: $posting->canonical_url,
                source: $posting->source,
                createdAt: $posting->created_at?->toIso8601String(),
            ));
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

    public function canonicalUrlForUser(string $uuid, int $userId): ?string
    {
        $url = StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->value('canonical_url');

        return $url !== null ? (string) $url : null;
    }

    public function recordApplyDestination(string $uuid, int $userId, string $destination, ?string $atsKind): void
    {
        DB::transaction(static fn (): bool => (bool) StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first(self::LIST_COLUMNS)
            ?->update(['apply_destination' => $destination, 'ats_kind' => $atsKind]));
    }

    public function unlinkResolution(string $uuid, int $userId): bool
    {
        return DB::transaction(static fn (): bool => (bool) StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first(self::LIST_COLUMNS)
            ?->update(['preferred_source_id' => null, 'apply_destination' => null]));
    }

    /**
     * One set-based UPDATE on purpose: expiry is a scheduled sweep over many
     * rows, and `is_expired` flips are recorded on the row itself
     * (`expired_at` + `alive_check_method`) rather than per-row activity.
     */
    public function expireUnseenSince(int $userId, DateTimeImmutable $cutoff): int
    {
        return DB::transaction(static fn (): int => StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->where('is_expired', false)
            ->where('last_seen_at', '<=', $cutoff)
            ->update([
                'is_expired' => true,
                'expired_at' => now(),
                'alive_check_method' => 'harvest_absence',
            ]));
    }

    public function attachPastedText(string $uuid, int $userId, string $text): bool
    {
        return DB::transaction(static function () use ($uuid, $userId, $text): bool {
            $posting = StudioPostingEloquentModel::query()
                ->ownedBy($userId)
                ->where('uuid', $uuid)
                ->where('status', 'reference')
                ->lockForUpdate()
                ->first(self::LIST_COLUMNS);

            if ($posting === null) {
                return false;
            }

            StudioPostingTextEloquentModel::query()->create([
                'user_id' => $userId,
                'posting_id' => $posting->id,
                'ladder_step' => 'candidate_pasted',
                'completeness' => 'full',
                'text' => $text,
                'char_count' => mb_strlen($text),
                'fetched_at' => now(),
            ]);

            return $posting->update(['status' => 'new']);
        });
    }

    public function requirementTargets(string $uuid, int $userId): ?array
    {
        $postingId = StudioPostingEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->value('id');

        if ($postingId === null) {
            return null;
        }

        return StudioRequirementEloquentModel::query()
            ->ownedBy($userId)
            ->where('posting_id', $postingId)
            ->toBase()
            ->get(['canonical_name', 'raw_text'])
            ->map(static fn (object $requirement): string => trim((string) ($requirement->raw_text !== null && $requirement->raw_text !== '' ? $requirement->raw_text : $requirement->canonical_name)))
            ->filter(static fn (string $text): bool => $text !== '')
            ->values()
            ->all();
    }

    public function requirementsForText(int $postingId, int $userId, string $textHash): array
    {
        return StudioRequirementEloquentModel::query()
            ->ownedBy($userId)
            ->where('posting_id', $postingId)
            ->where('source_text_hash', $textHash)
            ->toBase()
            ->get(['id', 'canonical_name', 'raw_text'])
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'canonical_name' => (string) $row->canonical_name,
                'raw_text' => (string) $row->raw_text,
            ])
            ->values()
            ->all();
    }

    public function storeRequirements(int $postingId, int $userId, string $textHash, array $requirements, array $provenance): array
    {
        return DB::transaction(static function () use ($postingId, $userId, $textHash, $requirements, $provenance): array {
            $stored = [];

            foreach ($requirements as $requirement) {
                $row = StudioRequirementEloquentModel::query()->create([
                    'user_id' => $userId,
                    'posting_id' => $postingId,
                    'canonical_name' => $requirement['canonical_name'],
                    'raw_text' => $requirement['raw_text'],
                    'tag' => $requirement['tag']->value,
                    'nature' => $requirement['nature']->value,
                    'source_text_hash' => $textHash,
                    'extracted_by_provider' => $provenance['provider'],
                    'extracted_by_model' => $provenance['model'],
                    'prompt_version' => $provenance['prompt_version'],
                ]);

                $stored[] = ['id' => $row->id, 'canonical_name' => $requirement['canonical_name'], 'raw_text' => $requirement['raw_text']];
            }

            return $stored;
        });
    }

    public function dismissRequirement(string $uuid, int $userId): bool
    {
        return (bool) StudioRequirementEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->delete();
    }
}
