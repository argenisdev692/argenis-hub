<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Enums\PostingStatus;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutJobPostingEloquentModel;

/**
 * Posting writes are transactional; source links are idempotent
 * (firstOrCreate on the pivot unique).
 */
final readonly class EloquentJobPostingRepository implements JobPostingRepositoryPort
{
    public function findByFingerprint(string $fingerprint): ?ScoutJobPostingEloquentModel
    {
        return ScoutJobPostingEloquentModel::query()->where('fingerprint', $fingerprint)->first();
    }

    public function create(array $attributes): ScoutJobPostingEloquentModel
    {
        return DB::transaction(
            static fn (): ScoutJobPostingEloquentModel => ScoutJobPostingEloquentModel::query()->create($attributes),
        );
    }

    public function attachSource(ScoutJobPostingEloquentModel $posting, int $sourceId): void
    {
        DB::transaction(static function () use ($posting, $sourceId): void {
            $exists = DB::table('scout_job_posting_sources')
                ->where('posting_id', $posting->id)
                ->where('source_id', $sourceId)
                ->exists();

            if (! $exists) {
                DB::table('scout_job_posting_sources')->insert([
                    'posting_id' => $posting->id,
                    'source_id' => $sourceId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function markExpired(int $postingId): void
    {
        DB::transaction(static function () use ($postingId): void {
            ScoutJobPostingEloquentModel::query()
                ->where('id', $postingId)
                ->where('status', PostingStatus::Active->value)
                ->update(['status' => PostingStatus::Expired->value, 'updated_at' => now()]);
        });
    }
}
