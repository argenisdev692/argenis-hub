<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Persistence\Repositories;

use Modules\VideoEdits\Domain\Ports\TranscriptStorePort;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditTranscriptEloquentModel;

/**
 * Transcript persistence for US-11 reuse.
 *
 * The reuse lookup joins through `video_edits` so it can filter on the owner:
 * matching only on the content fingerprint would hand one user's transcribed
 * speech to another who happened to upload the same file.
 */
final readonly class EloquentTranscriptStore implements TranscriptStorePort
{
    public function findReusable(int $userId, string $sourceFingerprint): ?Transcript
    {
        $stored = VideoEditTranscriptEloquentModel::query()
            ->where('source_fingerprint', $sourceFingerprint)
            ->whereHas('videoEdit', static fn ($query) => $query->where('user_id', $userId))
            ->latest('created_at')
            ->first();

        return $stored === null ? null : Transcript::fromArray($stored->payload);
    }

    public function store(
        int $videoEditId,
        string $sourceFingerprint,
        Transcript $transcript,
        string $provider,
        string $model,
    ): void {
        VideoEditTranscriptEloquentModel::query()->updateOrCreate(
            ['video_edit_id' => $videoEditId],
            [
                'source_fingerprint' => $sourceFingerprint,
                'provider' => $provider,
                'model' => $model,
                'language' => $transcript->language,
                'word_count' => $transcript->wordCount(),
                'payload' => $transcript->toArray(),
            ],
        );
    }
}
