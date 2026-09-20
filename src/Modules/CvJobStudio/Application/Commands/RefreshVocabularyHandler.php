<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioProfileRepositoryPort;

/**
 * Vocabulary refresh from the profile's own stack (T-027/T-028, FR-32):
 * `stack_must` terms seed as strong vocabulary, `stack_reject` as never_seed.
 * Refresh triggers (cache-miss, stale-week, low-yield, forced) live on the
 * caller; this handler is the idempotent write.
 */
final readonly class RefreshVocabularyHandler
{
    public function __construct(private StudioProfileRepositoryPort $profiles) {}

    public function handle(string $profileUuid, int $userId): int
    {
        $profile = $this->profiles->snapshotForUser($profileUuid, $userId)
            ?? throw RecordNotFoundException::of('profile', $profileUuid);

        return $this->profiles->seedVocabulary($profile->id, $userId, $profile->stackMust, $profile->stackReject);
    }
}
