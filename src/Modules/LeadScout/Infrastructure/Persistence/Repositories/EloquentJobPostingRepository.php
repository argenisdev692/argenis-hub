<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Entities\JobPosting;
use Modules\LeadScout\Domain\Enums\PostingStatus;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Domain\ValueObjects\NewJobPosting;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutJobPostingEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Mappers\JobPostingMapper;

final readonly class EloquentJobPostingRepository implements JobPostingRepositoryPort
{
    public function byUuid(string $uuid): ?JobPosting
    {
        $model = ScoutJobPostingEloquentModel::query()->where('uuid', $uuid)->first();

        return $model === null ? null : JobPostingMapper::toEntity($model);
    }

    public function idByFingerprint(string $fingerprint): ?int
    {
        $id = ScoutJobPostingEloquentModel::query()->where('fingerprint', $fingerprint)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function forCompany(int $companyId): array
    {
        return ScoutJobPostingEloquentModel::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->get()
            ->map(JobPostingMapper::toEntity(...))
            ->values()
            ->all();
    }

    public function activeForCompany(int $companyId): array
    {
        return ScoutJobPostingEloquentModel::query()
            ->where('company_id', $companyId)
            ->where('status', PostingStatus::Active->value)
            ->orderByDesc('published_at')
            ->get()
            ->map(JobPostingMapper::toEntity(...))
            ->values()
            ->all();
    }

    public function create(NewJobPosting $posting): JobPosting
    {
        return JobPostingMapper::toEntity(ScoutJobPostingEloquentModel::query()->create([
            'company_id' => $posting->companyId,
            'fingerprint' => $posting->fingerprint,
            'company_name' => $posting->companyName,
            'title' => $posting->title,
            'location' => $posting->location,
            'country' => $posting->country,
            'remote_mode' => $posting->remoteMode->value,
            'contract_type' => $posting->contractType->value,
            'language' => $posting->language,
            'published_at' => $posting->publishedAt,
            'status' => $posting->status->value,
            'source_url' => $posting->sourceUrl,
            'company_url' => $posting->companyUrl,
            'body_text' => $posting->bodyText,
        ]));
    }

    public function attachSource(int $postingId, int $sourceId): void
    {
        DB::table('scout_job_posting_sources')->insertOrIgnore([
            'posting_id' => $postingId,
            'source_id' => $sourceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function assignCompany(int $postingId, int $companyId): void
    {
        ScoutJobPostingEloquentModel::query()->findOrFail($postingId)->update(['company_id' => $companyId]);
    }

    public function expireActiveOlderThan(DateTimeImmutable $cutoff): int
    {
        return ScoutJobPostingEloquentModel::query()
            ->where('status', PostingStatus::Active->value)
            ->where(static function (Builder $query) use ($cutoff): void {
                $query->where('published_at', '<', $cutoff)
                    ->orWhere(static function (Builder $query) use ($cutoff): void {
                        $query->whereNull('published_at')->where('created_at', '<', $cutoff);
                    });
            })
            ->update(['status' => PostingStatus::Expired->value, 'updated_at' => now()]);
    }
}
