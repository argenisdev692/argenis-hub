<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Illuminate\Contracts\Cache\Repository as Cache;
use Modules\CvJobStudio\Application\Queries\GetOwnRatesHandler;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioApplicationRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;

/**
 * Employer-side outcome (T-080, FR-25): unknown / pending / no_reply /
 * rejected / screening / interview / offer / withdrawn. Triggers the channel
 * baseline recompute (outcomes adjust opportunity only — NFR-13) and drops
 * the cached own-rate panel after commit.
 */
final readonly class RecordOutcomeHandler
{
    public function __construct(
        private RecomputeChannelBaselinesHandler $baselines,
        private StudioPostingRepositoryPort $postings,
        private StudioApplicationRepositoryPort $applications,
        private TransactionPort $db,
        private Cache $cache,
    ) {}

    public function handle(string $postingUuid, string $outcome, ?string $note, int $userId): void
    {
        $this->db->atomic(function () use ($postingUuid, $outcome, $note, $userId): void {
            $postingId = $this->postings->idForUser($postingUuid, $userId)
                ?? throw new PostingNotFoundException("Posting {$postingUuid} not found.");

            $this->applications->recordOutcome($postingId, $userId, $outcome, $note);

            $this->baselines->handle($userId);
        });

        $this->cache->forget(GetOwnRatesHandler::cacheKey($userId));
    }
}
