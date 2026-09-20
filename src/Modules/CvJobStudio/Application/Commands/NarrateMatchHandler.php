<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\AllProvidersFailedException;
use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\MatchNarratorPort;
use Modules\CvJobStudio\Domain\Ports\StudioScoreRepositoryPort;

/**
 * Match narrative (T-156): 2–4 sentences from the stored breakdown only. If
 * every provider fails, the posting shows without narrative and its numbers
 * are unchanged (degrade path, T-152 test 3).
 */
final readonly class NarrateMatchHandler
{
    public function __construct(
        private StudioScoreRepositoryPort $scores,
        private MatchNarratorPort $narrator,
    ) {}

    /** @return array{why: string|null, gaps: string|null} */
    #[\NoDiscard]
    public function handle(string $scoreUuid, int $userId): array
    {
        $breakdown = $this->scores->narrationBreakdown($scoreUuid, $userId)
            ?? throw RecordNotFoundException::of('score', $scoreUuid);

        try {
            return $this->narrator->narrate($breakdown, $userId);
        } catch (AllProvidersFailedException) {
            return ['why' => null, 'gaps' => null];
        }
    }
}
