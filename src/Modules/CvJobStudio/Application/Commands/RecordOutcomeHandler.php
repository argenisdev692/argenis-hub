<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioApplicationEloquentModel;

/**
 * Employer-side outcome (T-080, FR-25): unknown / pending / no_reply /
 * rejected / screening / interview / offer / withdrawn. Triggers the channel
 * baseline recompute (outcomes adjust opportunity only — NFR-13).
 */
final readonly class RecordOutcomeHandler
{
    public function __construct(
        private RecomputeChannelBaselinesHandler $baselines,
        private StudioPostingRepositoryPort $postings,
        private TransactionPort $db,
    ) {}

    public function handle(string $postingUuid, string $outcome, ?string $note, int $userId): void
    {
        $this->db->atomic(function () use ($postingUuid, $outcome, $note, $userId): void {
            $posting = $this->postings->findByUuidForUser($postingUuid, $userId);

            if ($posting === null) {
                throw new PostingNotFoundException("Posting {$postingUuid} not found.");
            }

            StudioApplicationEloquentModel::query()->updateOrCreate(
                ['posting_id' => $posting->id, 'user_id' => $userId],
                ['outcome' => $outcome, 'outcome_at' => now(), 'outcome_note' => $note],
            );

            $this->baselines->handle($userId);
        });
    }
}
