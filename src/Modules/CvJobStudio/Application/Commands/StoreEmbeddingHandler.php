<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Ports\EmbeddingPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioEmbeddingEloquentModel;

/**
 * Write-once vector store (T-053, T-122, RK-9): the same text twice charges
 * once — an existing row for (owner, model) with the same content hash is
 * returned untouched. Coverage: bullets, CV titles, posting titles,
 * responsibilities and requirements, one row per content hash.
 */
final readonly class StoreEmbeddingHandler
{
    public function __construct(private EmbeddingPort $embeddings) {}

    #[\NoDiscard]
    public function handle(string $ownerType, int $ownerId, string $text, int $userId): StudioEmbeddingEloquentModel
    {
        $hash = hash('sha256', $text);

        $existing = StudioEmbeddingEloquentModel::query()
            ->where('user_id', $userId)
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->first();

        if ($existing !== null && $existing->content_hash === $hash) {
            return $existing;
        }

        $result = $this->embeddings->embed([$text]);
        $vector = $result['vectors'][0] ?? [];

        if ($existing !== null) {
            $existing->update([
                'model' => $result['model'],
                'dims' => $result['dims'],
                'content_hash' => $hash,
                'embedding' => $vector,
            ]);

            return $existing->refresh();
        }

        return StudioEmbeddingEloquentModel::query()->create([
            'user_id' => $userId,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'model' => $result['model'],
            'dims' => $result['dims'],
            'content_hash' => $hash,
            'embedding' => $vector,
        ]);
    }
}
