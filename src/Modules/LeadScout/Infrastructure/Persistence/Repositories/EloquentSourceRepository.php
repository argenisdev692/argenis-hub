<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Entities\Source;
use Modules\LeadScout\Domain\Enums\FetchMethod;
use Modules\LeadScout\Domain\Enums\FetchStatus;
use Modules\LeadScout\Domain\Enums\SourceStatus;
use Modules\LeadScout\Domain\Enums\SourceType;
use Modules\LeadScout\Domain\Ports\SourceRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutFetchAttemptEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Mappers\SourceMapper;

final readonly class EloquentSourceRepository implements SourceRepositoryPort
{
    public function all(): array
    {
        return ScoutSourceEloquentModel::query()
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get()
            ->map(SourceMapper::toEntity(...))
            ->values()
            ->all();
    }

    public function byUuid(string $uuid): ?Source
    {
        $model = ScoutSourceEloquentModel::query()->where('uuid', $uuid)->first();

        return $model === null ? null : SourceMapper::toEntity($model);
    }

    public function configure(Source $source, SourceStatus $status, int $frequencyMinutes, ?DateTimeImmutable $termsReviewedAt): Source
    {
        $model = ScoutSourceEloquentModel::query()->findOrFail($source->id);
        $model->update([
            'status' => $status->value,
            'frequency_minutes' => $frequencyMinutes,
            'terms_reviewed_at' => $termsReviewedAt,
        ]);

        return SourceMapper::toEntity($model);
    }

    public function saveCursor(Source $source, string $cursor): void
    {
        $this->write($source, ['last_cursor' => $cursor]);
    }

    public function markRun(Source $source, DateTimeImmutable $at): void
    {
        $this->write($source, ['last_run_at' => $at]);
    }

    public function setStatus(Source $source, SourceStatus $status): void
    {
        $this->write($source, ['status' => $status->value]);
    }

    public function recordAttempt(Source $source, FetchStatus $status, int $durationMs, ?string $error): void
    {
        ScoutFetchAttemptEloquentModel::query()->create([
            'source_id' => $source->id,
            'method' => $source->type === SourceType::Rss ? FetchMethod::Rss->value : FetchMethod::Api->value,
            'status' => $status->value,
            'duration_ms' => $durationMs,
            'error' => $error === null ? null : mb_substr($error, 0, 255),
        ]);
    }

    /**
     * Model update, so the activity log still sees status changes.
     *
     * @param  array<string, mixed>  $changes
     */
    private function write(Source $source, array $changes): void
    {
        ScoutSourceEloquentModel::query()->findOrFail($source->id)->update($changes);
    }
}
