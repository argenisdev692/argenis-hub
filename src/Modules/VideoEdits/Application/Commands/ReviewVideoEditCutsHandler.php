<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\VideoEdits\Application\DTOs\ReviewVideoEditCutsData;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Domain\Exceptions\InvalidCutReviewException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditStateConflictException;
use Modules\VideoEdits\Domain\Ports\AiCutReviewStorePort;
use Modules\VideoEdits\Domain\Ports\VideoEditProcessingDispatcherPort;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Domain\ValueObjects\AiCutReview;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Resolves the review of AI-proposed cuts and re-queues the edit for its render
 * pass (human-in-the-loop, OWASP LLM06).
 *
 * The resolution is written in the same compare-and-set statement that moves
 * `awaiting_review → queued` (AD-9): two tabs answering at once, or the owner
 * racing the expiry sweep, settle in the database and exactly one answer wins.
 */
final readonly class ReviewVideoEditCutsHandler
{
    public function __construct(
        private VideoEditRepositoryPort $edits,
        private AiCutReviewStorePort $reviews,
        private VideoEditProcessingDispatcherPort $processing,
        private AuditPort $audit,
    ) {}

    /**
     * @throws VideoEditNotFoundException
     * @throws VideoEditStateConflictException
     * @throws InvalidCutReviewException
     */
    #[\NoDiscard]
    public function handle(string $uuid, Authenticatable $user, ReviewVideoEditCutsData $data): VideoEditEloquentModel
    {
        $userId = (int) $user->getAuthIdentifier();
        $edit = $this->edits->findOwnedByUuid($uuid, $userId)
            ?? throw VideoEditNotFoundException::forUuid($uuid);

        $review = $this->pendingReview($edit)
            ?? throw VideoEditStateConflictException::invalidState($edit->status);

        $unknownCutIds = $review->unknownCutIds($data->approvedCutIds);

        if ($unknownCutIds !== []) {
            throw InvalidCutReviewException::unknownCuts($unknownCutIds);
        }

        $resolved = $review->resolve($data->approvedCutIds, CarbonImmutable::now());

        if (! $this->requeue($uuid, $resolved)) {
            throw VideoEditStateConflictException::invalidState(
                $this->edits->findByUuid($uuid)?->status ?? $edit->status,
            );
        }

        // Counts only: the cut texts are the owner's speech (OWASP §9).
        $this->audit->log(
            'video_edit.cuts_reviewed',
            null,
            ['edit_uuid' => $uuid, 'proposed' => count($review->cuts), 'approved' => count($resolved->approvedCutIds)],
            $user,
            'video-edits.video-edit',
        );

        $this->processing->dispatch($uuid);

        return $this->edits->findOwnedWithDetails($uuid, $userId)
            ?? throw VideoEditNotFoundException::forUuid($uuid);
    }

    /**
     * An unanswered review resolves as "keep everything": the edit still
     * renders, with no AI cut applied that nobody approved.
     */
    public function handleExpired(string $uuid): bool
    {
        $edit = $this->edits->findByUuid($uuid);

        if ($edit === null) {
            return false;
        }

        $review = $this->pendingReview($edit);

        if ($review === null) {
            return false;
        }

        if (! $this->requeue($uuid, $review->resolve([], CarbonImmutable::now(), byExpiry: true))) {
            return false;
        }

        $this->audit->log(
            'video_edit.cuts_review_expired',
            null,
            ['edit_uuid' => $uuid, 'proposed' => count($review->cuts), 'approved' => 0],
            null,
            'video-edits.video-edit',
        );

        $this->processing->dispatch($uuid);

        return true;
    }

    private function pendingReview(VideoEditEloquentModel $edit): ?AiCutReview
    {
        if ($edit->status !== VideoEditStatus::AwaitingReview) {
            return null;
        }

        $review = $this->reviews->forEdit($edit->id);

        return $review?->isResolved() === false ? $review : null;
    }

    private function requeue(string $uuid, AiCutReview $resolved): bool
    {
        return $this->edits->transitionStatus($uuid, [VideoEditStatus::AwaitingReview], VideoEditStatus::Queued, [
            'ai_review' => $resolved->toArray(),
            'review_expires_at' => null,
            'queued_at' => CarbonImmutable::now(),
            'progress_percent' => 0,
            'current_stage' => null,
        ]);
    }
}
