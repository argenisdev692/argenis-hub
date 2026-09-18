<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Domain\Services\OpportunityCalculator;
use Modules\CvJobStudio\Domain\Services\OpportunityPolicy;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;

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
        private TransactionPort $db,
    ) {}

    public function handle(int $userId, bool $neutral = false): int
    {
        return $this->db->atomic(function () use ($userId, $neutral): int {
            $scores = StudioScoreEloquentModel::query()
                ->where('user_id', $userId)
                ->where(static function ($query): void {
                    $query->whereNull('priority_computed_at')
                        ->orWhere('priority_computed_at', '<=', now()->subHours(6));
                })
                ->with('posting')
                ->lockForUpdate()
                ->get();

            foreach ($scores as $score) {
                $posting = $score->posting;
                $policy = OpportunityPolicy::fromConfig(
                    $posting->profile->rules['opportunity'] ?? config('cv-job-studio.opportunity'),
                    $neutral,
                );

                $result = $this->opportunity->calculate([
                    'channel' => $posting->discovery_channel,
                    'age_days' => $posting->posted_at !== null ? (float) $posting->posted_at->diffInDays(now()) : null,
                    'posted_at' => $posting->posted_at?->format('Y-m-d'),
                    'relist_count' => $posting->sightings()->count(),
                    'text' => (string) $posting->texts()->orderByDesc('id')->first()?->text,
                    'title' => $posting->title,
                    'profile_min_step' => null,
                    'profile_max_step' => null,
                ], $policy);

                $score->update([
                    'o_components' => $result['components'],
                    'opportunity_static' => $result['factor'],
                    'apply_priority' => (float) $score->total_score * $result['factor'],
                    'priority_computed_at' => now(),
                ]);
            }

            return $scores->count();
        });
    }
}
