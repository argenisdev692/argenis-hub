<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Modules\VideoEdits\Domain\ValueObjects\AiCutReview;

/**
 * Persistence for the owner's review of AI-proposed cuts.
 *
 * Resolving a review is NOT here: it must land in the same statement as the
 * `awaiting_review → queued` move, so it goes through
 * {@see VideoEditRepositoryPort::transitionStatus()} as an attribute.
 */
interface AiCutReviewStorePort
{
    public function store(int $videoEditId, AiCutReview $review): void;

    public function forEdit(int $videoEditId): ?AiCutReview;
}
