<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Opportunity factor per scored posting (T-130, FR-46): channel × freshness ×
 * ghost-risk × seniority, each stored with value, grade, source, n, k and
 * reason. Labelled an estimate with its sample size (NFR-14); neutral mode
 * returns exactly 1.
 */
final readonly class OpportunityCalculator
{
    public function __construct(
        private FreshnessDecay $freshness,
        private GhostSignalDetector $ghosts,
        private TitleLevelClassifier $levels,
    ) {}

    /**
     * @param  array{channel: string|null, age_days: float|null, posted_at: string|null, relist_count: int, text: string, title: string, profile_min_step: int|null, profile_max_step: int|null}  $posting
     * @return array{factor: float, components: array<string, array{value: float, grade: string, source: string, n: int, k: int, reason: string}>}
     */
    #[\NoDiscard]
    public function calculate(array $posting, OpportunityPolicy $policy): array
    {
        if ($policy->neutral) {
            return ['factor' => 1.0, 'components' => []];
        }

        $channel = $policy->channel($posting['channel'] ?? 'unknown');

        $freshnessValue = $this->freshness->factor(
            $posting['age_days'],
            $policy->freshnessHalfLifeDays,
            $policy->freshnessFloor,
            $policy->unknownAgePenalty,
        );

        $ghostSignals = $this->ghosts->signals([
            'posted_at' => $posting['posted_at'],
            'age_days' => $posting['age_days'],
            'relist_count' => $posting['relist_count'],
            'text' => $posting['text'],
            'channel' => $posting['channel'],
        ]);
        $ghostValue = $ghostSignals === [] ? 1.0 : max($policy->ghostFloor, 1.0 - $policy->ghostPerSignal * count($ghostSignals));

        $titleStep = $this->levels->stepOf($posting['title']);
        $seniorityValue = $this->seniorityFactor($titleStep, $posting['profile_min_step'], $posting['profile_max_step'], $policy);

        $factor = $channel['value'] * $freshnessValue * $ghostValue * $seniorityValue;

        return [
            'factor' => $factor,
            'components' => [
                'channel' => [...$channel, 'n' => 0, 'k' => 0, 'reason' => 'Policy '.($channel['grade']).' — '.($channel['source'])],
                'freshness' => [
                    'value' => $freshnessValue, 'grade' => 'C', 'source' => 'policy(Q19b)',
                    'n' => 0, 'k' => 0,
                    'reason' => $posting['age_days'] === null ? 'Unknown age — penalised and flagged.' : "Age {$posting['age_days']}d decayed.",
                ],
                'ghost' => [
                    'value' => $ghostValue, 'grade' => 'C', 'source' => 'policy(Q19b)',
                    'n' => 0, 'k' => 0,
                    'reason' => $ghostSignals === [] ? 'No ghost signals.' : 'Signals: '.implode(', ', $ghostSignals).'.',
                ],
                'seniority' => [
                    'value' => $seniorityValue, 'grade' => 'C', 'source' => 'policy(Q19b)',
                    'n' => 0, 'k' => 0,
                    'reason' => $titleStep === null ? 'Title level unread.' : "Title step {$titleStep}.",
                ],
            ],
        ];
    }

    private function seniorityFactor(?int $titleStep, ?int $minStep, ?int $maxStep, OpportunityPolicy $policy): float
    {
        if ($titleStep === null || $minStep === null || $maxStep === null) {
            return 1.0;
        }

        if ($titleStep < $minStep) {
            return $policy->seniorityBelowBand;
        }

        if ($titleStep > $maxStep) {
            return $policy->seniorityAboveBand;
        }

        return 1.0;
    }
}
