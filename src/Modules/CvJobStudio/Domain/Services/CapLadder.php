<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Cap ladder (FR-19): the lowest applicable cap wins, applied once, rounded
 * once. An unreadable posting can never ride on location alone into the
 * recommended band; credential/floor/language gaps and weak evidence cap
 * below it. The uncapped value is always kept beside the total (FR-20) so
 * the reach table stays computable.
 */
final readonly class CapLadder
{
    /**
     * @param  array{readable: bool, credential_ok: bool, evidence_ok: bool}  $context
     * @param  list<array{max: float, reason: string}>  $caps  ascending by max
     * @return array{total: float, raw: float, caps_applied: list<array{max: float, reason: string}>, cap_reason: ?string}
     */
    #[\NoDiscard]
    public function apply(float $raw, array $context, array $caps): array
    {
        $applicable = [];

        if (! $context['readable']) {
            $applicable[] = $this->capFor('unreadable_requirements', $caps);
        }

        if (! $context['credential_ok']) {
            $applicable[] = $this->capFor('credential_or_floor_or_language', $caps);
        }

        if (! $context['evidence_ok']) {
            $applicable[] = $this->capFor('weak_evidence', $caps);
        }

        $applicable = array_filter($applicable);

        if ($applicable === []) {
            return ['total' => round($raw), 'raw' => $raw, 'caps_applied' => [], 'cap_reason' => null];
        }

        usort($applicable, static fn (array $a, array $b): int => $a['max'] <=> $b['max']);
        $lowest = array_first($applicable);

        return [
            'total' => round(min($raw, $lowest['max'])),
            'raw' => $raw,
            'caps_applied' => array_values($applicable),
            'cap_reason' => $lowest['reason'],
        ];
    }

    /**
     * @param  list<array{max: float, reason: string}>  $caps
     * @return array{max: float, reason: string}|null
     */
    private function capFor(string $reason, array $caps): ?array
    {
        foreach ($caps as $cap) {
            if ($cap['reason'] === $reason) {
                return $cap;
            }
        }

        return null;
    }
}
