<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Opportunity policy value object (T-125, FR-51): parsed from
 * `studio_profiles.rules.opportunity`, rejects any factor outside [0.1, 1]
 * or missing grade/source, and offers a neutral mode (Q = 1) that reproduces
 * pure fit order exactly (SC-13).
 */
final readonly class OpportunityPolicy
{
    /**
     * @param  array<string, array{value: float, grade: string, source: string}>  $channels
     */
    private function __construct(
        public array $channels,
        public float $unknownAgePenalty,
        public float $freshnessHalfLifeDays,
        public float $freshnessFloor,
        public float $seniorityBelowBand,
        public float $seniorityAboveBand,
        public float $ghostPerSignal,
        public float $ghostFloor,
        public bool $neutral = false,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    #[\NoDiscard]
    public static function fromConfig(array $config, bool $neutral = false): self
    {
        $channels = [];

        foreach ($config['channel'] ?? [] as $channel => $entry) {
            $value = (float) ($entry['value'] ?? 0.0);

            if ($value < 0.1 || $value > 1.0) {
                throw new \InvalidArgumentException("Opportunity channel '{$channel}' out of [0.1, 1].");
            }

            if (($entry['grade'] ?? '') === '' || ($entry['source'] ?? '') === '') {
                throw new \InvalidArgumentException("Opportunity channel '{$channel}' needs grade + source.");
            }

            $channels[$channel] = ['value' => $value, 'grade' => $entry['grade'], 'source' => $entry['source']];
        }

        return new self(
            $channels,
            (float) ($config['unknown_age_penalty'] ?? 0.9),
            (float) ($config['freshness_half_life_days'] ?? 14.0),
            (float) ($config['freshness_floor'] ?? 0.5),
            (float) ($config['seniority_below_band'] ?? 0.9),
            (float) ($config['seniority_above_band'] ?? 0.7),
            (float) ($config['ghost_per_signal'] ?? 0.1),
            (float) ($config['ghost_floor'] ?? 0.5),
            $neutral,
        );
    }

    /** @return array{value: float, grade: string, source: string} */
    #[\NoDiscard]
    public function channel(string $channel): array
    {
        return $this->channels[$channel] ?? $this->channels['unknown'];
    }
}
