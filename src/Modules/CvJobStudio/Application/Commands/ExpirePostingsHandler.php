<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;

/**
 * Liveness and expiry (T-016, CHG-3, FR-42): a posting absent from harvests
 * for the configured window is marked expired with date + method. Expired
 * postings keep their score, leave the apply list, and stay in market
 * insights — their requirements remain valid market signal.
 */
final readonly class ExpirePostingsHandler
{
    public function __construct(private StudioPostingRepositoryPort $postings) {}

    public function handle(int $userId): int
    {
        $days = (int) config('cv-job-studio.expiry_days', 14);

        return $this->postings->expireUnseenSince($userId, CarbonImmutable::now()->subDays($days));
    }
}
