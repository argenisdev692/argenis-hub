<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Persistence\Repositories;

use Modules\Post\Domain\Ports\PostAiGenerationRepositoryPort;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostAiGenerationEloquentModel;

final readonly class EloquentPostAiGenerationRepository implements PostAiGenerationRepositoryPort
{
    public function create(array $attributes): PostAiGenerationEloquentModel
    {
        return PostAiGenerationEloquentModel::query()->create($attributes);
    }

    public function findByUuid(string $uuid): ?PostAiGenerationEloquentModel
    {
        return PostAiGenerationEloquentModel::query()->where('uuid', $uuid)->first();
    }

    public function findByUuidForUser(string $uuid, int $userId): ?PostAiGenerationEloquentModel
    {
        return PostAiGenerationEloquentModel::query()
            ->where('uuid', $uuid)
            ->where('created_by', $userId)
            ->first();
    }

    public function update(PostAiGenerationEloquentModel $generation, array $attributes): PostAiGenerationEloquentModel
    {
        $generation->update($attributes);

        return $generation->refresh();
    }

    /**
     * A direct UPDATE rather than a find-then-save: the job reports a phase
     * several times per iteration and none of those writes needs the model
     * hydrated, so this stays one query instead of two.
     */
    public function markProgress(string $uuid, string $status, string $message, int $progress, int $iteration): void
    {
        PostAiGenerationEloquentModel::query()
            ->where('uuid', $uuid)
            ->update([
                'status' => $status,
                'stage_message' => $message,
                'progress' => $progress,
                'iteration' => $iteration,
                'updated_at' => now(),
            ]);
    }
}
