<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

/** Write-once vector rows, one per (owner, owner type) — RK-9. */
interface StudioEmbeddingRepositoryPort
{
    /** The content hash already embedded for this owner, or null when none is stored. */
    public function contentHashFor(int $userId, string $ownerType, int $ownerId): ?string;

    /**
     * Creates the owner's vector row or replaces its content in place.
     *
     * @param  list<float>  $vector
     */
    public function save(int $userId, string $ownerType, int $ownerId, string $model, int $dims, string $contentHash, array $vector): void;
}
