<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;

/**
 * Liveness and expiry (T-016, CHG-3, FR-42): a posting absent from harvests
 * for the configured window is marked expired with date + method. Expired
 * postings keep their score, leave the apply list, and stay in market
 * insights — their requirements remain valid market signal.
 */
final readonly class ExpirePostingsHandler
{
    public function __construct(private TransactionPort $db) {}

    public function handle(int $userId): int
    {
        return $this->db->atomic(static function () use ($userId): int {
            $days = (int) config('cv-job-studio.expiry_days', 14);

            return StudioPostingEloquentModel::query()
                ->ownedBy($userId)
                ->where('is_expired', false)
                ->where('last_seen_at', '<=', now()->subDays($days))
                ->update([
                    'is_expired' => true,
                    'expired_at' => now(),
                    'alive_check_method' => 'harvest_absence',
                ]);
        });
    }
}
