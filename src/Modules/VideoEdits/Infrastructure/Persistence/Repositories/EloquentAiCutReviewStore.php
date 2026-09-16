<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Persistence\Repositories;

use Modules\VideoEdits\Domain\Ports\AiCutReviewStorePort;
use Modules\VideoEdits\Domain\ValueObjects\AiCutReview;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;

/**
 * The cut review, stored on the edit itself — same reasoning as
 * {@see EloquentAiReportStore}: always read whole, never queried by field, and
 * gone with the edit on a hard delete. It holds transcript excerpts, so it must
 * not outlive the row.
 */
final readonly class EloquentAiCutReviewStore implements AiCutReviewStorePort
{
    public function store(int $videoEditId, AiCutReview $review): void
    {
        VideoEditEloquentModel::query()
            ->whereKey($videoEditId)
            ->update(['ai_review' => json_encode($review->toArray(), JSON_THROW_ON_ERROR)]);
    }

    public function forEdit(int $videoEditId): ?AiCutReview
    {
        $review = VideoEditEloquentModel::query()->whereKey($videoEditId)->value('ai_review');

        return is_array($review) ? AiCutReview::fromArray($review) : null;
    }
}
