<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Repositories;

use Modules\CvJobStudio\Domain\Ports\StudioEmbeddingRepositoryPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioEmbeddingEloquentModel;

final readonly class EloquentStudioEmbeddingRepository implements StudioEmbeddingRepositoryPort
{
    public function contentHashFor(int $userId, string $ownerType, int $ownerId): ?string
    {
        $hash = StudioEmbeddingEloquentModel::query()
            ->where('user_id', $userId)
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->value('content_hash');

        return $hash !== null ? (string) $hash : null;
    }

    public function save(int $userId, string $ownerType, int $ownerId, string $model, int $dims, string $contentHash, array $vector): void
    {
        StudioEmbeddingEloquentModel::query()->updateOrCreate(
            ['user_id' => $userId, 'owner_type' => $ownerType, 'owner_id' => $ownerId],
            ['model' => $model, 'dims' => $dims, 'content_hash' => $contentHash, 'embedding' => $vector],
        );
    }
}
