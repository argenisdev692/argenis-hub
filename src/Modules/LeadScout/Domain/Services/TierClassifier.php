<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

use Modules\LeadScout\Domain\Enums\ActivityStatus;
use Modules\LeadScout\Domain\Enums\DiscardReason;
use Modules\LeadScout\Domain\Enums\EmployeeRange;
use Modules\LeadScout\Domain\Enums\Tier;

/**
 * Tier assignment from explicit, configurable rules (spec US-4 CA-5/6/7/8,
 * plan §3.3). Thresholds come from `lead-scout.scoring`; every discard
 * keeps its reason (US-4 CA-9). `needs_research` is a mark, not a tier:
 * good fit, thin evidence → one extra cheap round before re-scoring.
 */
final readonly class TierClassifier
{
    /**
     * @param  array<int, string>  $factKeys  signal keys observed as facts
     * @param  array{solo_freelancer: bool, dead_or_absorbed: bool, inactive_agency: bool, outsourcer_large: bool, remote_zero: bool}  $flags
     * @return array{tier: Tier, discardReason: ?DiscardReason, needsResearch: bool}
     */
    #[\NoDiscard]
    public function classify(
        int $leadScore,
        int $confidence,
        int $technical,
        string $activityStatus,
        string $employeeRange,
        ?int $teamObserved,
        array $factKeys,
        array $flags,
        ?string $country,
        array $thresholds,
    ): array {
        if ($flags['outsourcer_large']) {
            return $this->discarded(DiscardReason::LargeOutsourcer);
        }

        if ($flags['solo_freelancer']) {
            return $this->discarded(DiscardReason::SoloFreelancer);
        }

        if ($flags['dead_or_absorbed']) {
            return $this->discarded(DiscardReason::DeadOrAcquired);
        }

        if ($flags['inactive_agency']) {
            return $this->discarded(DiscardReason::Inactive);
        }

        if ($technical < 30) {
            return $this->discarded(DiscardReason::LowTechnical);
        }

        if ($flags['remote_zero'] && $country !== 'PT') {
            return $this->discarded(DiscardReason::OnsiteAbroad);
        }

        $aScore = $thresholds['a_min_score'] ?? 80;
        $aConf = $thresholds['a_min_confidence'] ?? 70;
        $bScore = $thresholds['b_min_score'] ?? 65;
        $bConf = $thresholds['b_min_confidence'] ?? 50;

        $commercialFact = $this->hasCommercialFact($factKeys);

        if ($leadScore >= $aScore
            && $confidence >= $aConf
            && $commercialFact
            && $technical >= 60
            && $activityStatus === ActivityStatus::Active->value
            && $this->hasRealTeam($employeeRange, $teamObserved, $factKeys)
        ) {
            return ['tier' => Tier::A, 'discardReason' => null, 'needsResearch' => false];
        }

        if ($leadScore >= $bScore && $confidence >= $bConf) {
            return [
                'tier' => Tier::B,
                'discardReason' => null,
                'needsResearch' => $this->needsResearch($leadScore, $confidence, $thresholds, $activityStatus),
            ];
        }

        return [
            'tier' => Tier::C,
            'discardReason' => null,
            'needsResearch' => $this->needsResearch($leadScore, $confidence, $thresholds, $activityStatus),
        ];
    }

    /**
     * @param  array<int, string>  $factKeys
     */
    private function hasCommercialFact(array $factKeys): bool
    {
        foreach (['freelance_contract', 'accepts_external', 'agency_type', 'consultancy_type', 'multi_vacancies', 'fixed_job'] as $key) {
            if (in_array($key, $factKeys, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $factKeys
     */
    private function hasRealTeam(string $employeeRange, ?int $teamObserved, array $factKeys): bool
    {
        $observed = $teamObserved ?? $this->rangeMid($employeeRange);

        if ($observed !== null && $observed >= 5) {
            return true;
        }

        if ($observed !== null && $observed >= 2 && $observed <= 4) {
            return in_array('active_vacancy', $factKeys, true) || in_array('accepts_external', $factKeys, true);
        }

        return false;
    }

    private function rangeMid(string $range): ?int
    {
        return match ($range) {
            EmployeeRange::From2To4->value => 3,
            EmployeeRange::From5To10->value => 7,
            EmployeeRange::From11To50->value => 25,
            EmployeeRange::From51To200->value => 100,
            EmployeeRange::Over200->value => 250,
            default => null,
        };
    }

    private function needsResearch(int $leadScore, int $confidence, array $thresholds, string $activityStatus): bool
    {
        $bar = $thresholds['needs_research'] ?? [];

        if ($leadScore >= ($bar['high_score_low_confidence']['score'] ?? 80)
            && $confidence < ($bar['high_score_low_confidence']['confidence_below'] ?? 70)) {
            return true;
        }

        return $activityStatus === ActivityStatus::Unknown->value
            && $leadScore >= ($bar['unknown_activity_min_score'] ?? 65);
    }

    /**
     * @return array{tier: Tier, discardReason: ?DiscardReason, needsResearch: bool}
     */
    private function discarded(DiscardReason $reason): array
    {
        return [
            'tier' => Tier::Discarded,
            'discardReason' => $reason,
            'needsResearch' => false,
        ];
    }
}
