<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Ghost-posting risk signals (CHG-9): no date, age > 30d, re-listed sightings,
 * evergreen wording in ES/EN/PT, intermediary-only channel. Each signal is a
 * named reason; the policy caps the combined penalty.
 */
final readonly class GhostSignalDetector
{
    /** @var list<string> */
    private const array EVERGREEN_PATTERNS = [
        'always hiring', 'continuously hiring', 'evergreen', 'talent pool', 'future openings',
        'siempre contratando', 'contratación continua', 'bolsa de talento', 'futuras vacantes',
        'sempre a contratar', 'contratação contínua', 'bolsa de talentos', 'futuras vagas',
        'join our talent network', 'we are always looking',
    ];

    /**
     * @param  array{posted_at: string|null, age_days: float|null, relist_count: int, text: string, channel: string|null}  $posting
     * @return list<string> signal codes, empty when clean
     */
    #[\NoDiscard]
    public function signals(array $posting): array
    {
        $signals = [];

        if ($posting['posted_at'] === null) {
            $signals[] = 'no_date';
        }

        if (($posting['age_days'] ?? 0.0) > 30.0) {
            $signals[] = 'stale_over_30d';
        }

        if (($posting['relist_count'] ?? 0) >= 3) {
            $signals[] = 'relisted';
        }

        $text = mb_strtolower($posting['text']);

        foreach (self::EVERGREEN_PATTERNS as $pattern) {
            if (str_contains($text, $pattern)) {
                $signals[] = 'evergreen_wording';

                break;
            }
        }

        if (in_array($posting['channel'], ['aggregator', 'agency', 'social'], true)) {
            $signals[] = 'intermediary_only';
        }

        return $signals;
    }
}
