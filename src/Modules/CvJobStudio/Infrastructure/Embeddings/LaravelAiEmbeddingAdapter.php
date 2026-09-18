<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Embeddings;

use Laravel\Ai\Embeddings;
use Modules\CvJobStudio\Domain\Ports\EmbeddingPort;

/**
 * Embeddings through the official `laravel/ai` SDK (T-051, FR-17): cached,
 * 1536 dimensions, write-once per content hash at the store layer. Similarity
 * vectors are never mixed across models — an embedding outage delays work
 * instead of switching model (NFR-17, SC-19).
 */
final readonly class LaravelAiEmbeddingAdapter implements EmbeddingPort
{
    public function embed(array $texts): array
    {
        $response = Embeddings::for($texts)->dimensions(1536)->cache()->generate();

        return [
            'vectors' => $response->embeddings ?? [],
            'model' => $response->model ?? 'default',
            'dims' => 1536,
        ];
    }
}
