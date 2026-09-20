<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\CvJobStudio\Domain\Ports\StudioScoreRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Domain\Services\OpportunityCalculator;
use Modules\CvJobStudio\Domain\Services\OpportunityPolicy;

/**
 * Materialises `apply_priority = fit × opportunity` (T-131, FR-47): refreshed
 * when `priority_computed_at` is older than 6h, ordered portably. SC-13:
 * neutral mode reproduces pure fit order exactly — opportunity never alters
 * fit, only multiplies at read time.
 */
final readonly class RefreshApplyPriorityHandler
{
    public function __construct(
        private OpportunityCalculator $opportunity,
        private StudioScoreRepositoryPort $scores,
        private TransactionPort $db,
    ) {}

    public function handle(int $userId, bool $neutral = false): int
    {
        return $this->db->atomic(function () use ($userId, $neutral): int {
            $now = CarbonImmutable::now();
            $candidates = $this->scores->priorityCandidates($userId, $now->subHours(6));
            $priorities = [];

            foreach ($candidates as $candidate) {
                $policy = OpportunityPolicy::fromConfig(
                    $candidate['opportunity_rules'] ?? config('cv-job-studio.opportunity'),
                    $neutral,
                );

                $postedAt = $candidate['posted_at'] !== null ? CarbonImmutable::instance($candidate['posted_at']) : null;

                $result = $this->opportunity->calculate([
                    'channel' => $candidate['channel'],
                    'age_days' => $postedAt !== null ? (float) $postedAt->diffInDays($now) : null,
                    'posted_at' => $postedAt?->format('Y-m-d'),
                    'relist_count' => $candidate['relist_count'],
                    'text' => $candidate['text'],
                    'title' => $candidate['title'],
                    'profile_min_step' => null,
                    'profile_max_step' => null,
                ], $policy);

                $priorities[$candidate['score_id']] = [
                    'components' => $result['components'],
                    'factor' => $result['factor'],
                    'priority' => $candidate['total_score'] * $result['factor'],
                ];
            }

            $this->scores->recordPriorities($priorities);

            return count($candidates);
        });
    }
}
