<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Shared\Domain\Ports\StoragePort;

/**
 * Scheduled backstop (AD-14, D17):
 *  - an edit with no progress write for longer than the stale limit is marked
 *    `processing_timeout` (a crashed worker never calls `failed()`, and PCNTL
 *    timeouts do not exist on Windows);
 *  - a cut review left unanswered past its deadline resolves as "keep
 *    everything", so the edit renders without AI cuts instead of holding the
 *    owner's active slot forever;
 *  - a draft never submitted is deleted together with anything uploaded for it.
 */
final readonly class SweepStaleVideoEditsHandler
{
    private const int BATCH = 100;

    public function __construct(
        private VideoEditRepositoryPort $edits,
        private MarkVideoEditFailedHandler $failures,
        private ReviewVideoEditCutsHandler $reviews,
        private StoragePort $storage,
        private Config $config,
    ) {}

    /**
     * @return array{timed_out: int, expired_reviews: int, expired_drafts: int}
     */
    public function handle(): array
    {
        $now = CarbonImmutable::now();
        $timedOut = 0;
        $expiredReviews = 0;
        $expiredDrafts = 0;

        $staleBefore = $now->subMinutes((int) $this->config->get('video-edit.retention.stale_processing_minutes'));

        foreach ($this->edits->staleProcessingUuids($staleBefore, self::BATCH) as $uuid) {
            $this->failures->handleTimeout($uuid);
            $timedOut++;
        }

        foreach ($this->edits->expiredReviewUuids($now, self::BATCH) as $uuid) {
            if ($this->reviews->handleExpired($uuid)) {
                $expiredReviews++;
            }
        }

        $draftsBefore = $now->subHours((int) $this->config->get('video-edit.retention.draft_hours'));

        foreach ($this->edits->expiredDraftUuids($draftsBefore, self::BATCH) as $uuid) {
            $deleted = $this->edits->deleteDraft($uuid, function (VideoEditEloquentModel $draft): void {
                foreach ($draft->sources->pluck('storage_path')->filter() as $path) {
                    $this->storage->delete((string) $path);
                }
            });

            if ($deleted) {
                $expiredDrafts++;
            }
        }

        return ['timed_out' => $timedOut, 'expired_reviews' => $expiredReviews, 'expired_drafts' => $expiredDrafts];
    }
}
