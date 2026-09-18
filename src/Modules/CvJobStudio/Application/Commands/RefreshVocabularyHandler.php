<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioProfileRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioVocabularyEloquentModel;

/**
 * Vocabulary refresh from the profile's own stack (T-027/T-028, FR-32):
 * `stack_must` terms seed as strong vocabulary, `stack_reject` as never_seed.
 * Refresh triggers (cache-miss, stale-week, low-yield, forced) live on the
 * caller; this handler is the idempotent write.
 */
final readonly class RefreshVocabularyHandler
{
    public function __construct(
        private StudioProfileRepositoryPort $profiles,
        private TransactionPort $db,
    ) {}

    public function handle(string $profileUuid, int $userId): int
    {
        return $this->db->atomic(function () use ($profileUuid, $userId): int {
            $profile = $this->profiles->findByUuidForUser($profileUuid, $userId);

            if ($profile === null) {
                throw new PostingNotFoundException("Profile {$profileUuid} not found.");
            }

            $count = 0;

            foreach ($profile->stack_must ?? [] as $term) {
                StudioVocabularyEloquentModel::query()->updateOrCreate(
                    ['profile_id' => $profile->id, 'term' => $term, 'language' => 'en'],
                    ['user_id' => $userId, 'kind' => 'strong', 'refreshed_at' => now()],
                );
                $count++;
            }

            foreach ($profile->stack_reject ?? [] as $term) {
                StudioVocabularyEloquentModel::query()->updateOrCreate(
                    ['profile_id' => $profile->id, 'term' => $term, 'language' => 'en'],
                    ['user_id' => $userId, 'kind' => 'never_seed', 'refreshed_at' => now()],
                );
                $count++;
            }

            return $count;
        });
    }
}
