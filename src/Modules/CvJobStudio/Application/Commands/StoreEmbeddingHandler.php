<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Ports\EmbeddingPort;
use Modules\CvJobStudio\Domain\Ports\StudioEmbeddingRepositoryPort;

/**
 * Write-once vector store (T-053, T-122, RK-9): the same text twice charges
 * once — an existing row for (owner, model) with the same content hash is
 * left untouched. Coverage: bullets, CV titles, posting titles,
 * responsibilities and requirements, one row per content hash.
 */
final readonly class StoreEmbeddingHandler
{
    public function __construct(
        private EmbeddingPort $embeddings,
        private StudioEmbeddingRepositoryPort $vectors,
    ) {}

    public function handle(string $ownerType, int $ownerId, string $text, int $userId): void
    {
        $hash = hash('sha256', $text);

        if ($this->vectors->contentHashFor($userId, $ownerType, $ownerId) === $hash) {
            return;
        }

        $result = $this->embeddings->embed([$text]);

        $this->vectors->save(
            $userId,
            $ownerType,
            $ownerId,
            $result['model'],
            $result['dims'],
            $hash,
            array_values($result['vectors'][0] ?? []),
        );
    }
}
