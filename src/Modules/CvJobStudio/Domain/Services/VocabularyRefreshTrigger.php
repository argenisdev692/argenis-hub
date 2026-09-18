<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Refresh triggers as data (T-028, FR-32, A-9): cache-miss, stale-week,
 * low-yield, or forced. A scheduler needs no redesign — it reads the same
 * decision this pure service returns.
 */
final readonly class VocabularyRefreshTrigger
{
    /**
     * @param  array{terms_count: int, refreshed_at: string|null, last_yield: float|null}  $state
     * @return array{refresh: bool, trigger: string}
     */
    #[\NoDiscard]
    public function evaluate(array $state, bool $forced, int $staleDays = 7, float $lowYield = 0.1): array
    {
        if ($forced) {
            return ['refresh' => true, 'trigger' => 'forced'];
        }

        if ($state['terms_count'] === 0) {
            return ['refresh' => true, 'trigger' => 'cache-miss'];
        }

        if ($state['refreshed_at'] === null || strtotime($state['refreshed_at']) <= strtotime("-{$staleDays} days")) {
            return ['refresh' => true, 'trigger' => 'stale-week'];
        }

        if (($state['last_yield'] ?? 1.0) < $lowYield) {
            return ['refresh' => true, 'trigger' => 'low-yield'];
        }

        return ['refresh' => false, 'trigger' => 'none'];
    }
}
