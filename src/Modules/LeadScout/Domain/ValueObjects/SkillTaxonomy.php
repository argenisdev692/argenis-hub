<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

/**
 * Deterministic skill vocabulary (spec FR-5, FR-10): canonical terms with
 * PT/ES/EN synonyms. A term found in the CV body is CONFIRMED and may score;
 * watch-list terms absent from the CV are POTENTIAL (require verification)
 * and never score (US-1 CA-2, CA-7). No IA, no embeddings (A10).
 */
final readonly class SkillTaxonomy
{
    /**
     * Canonical term → synonyms (all matched case-insensitively).
     *
     * @var array<string, list<string>>
     */
    public const array TERMS = [
        'laravel' => ['laravel'],
        'php' => ['php'],
        'vue' => ['vue', 'vue.js', 'vuejs'],
        'inertia' => ['inertia', 'inertia.js'],
        'livewire' => ['livewire'],
        'postgresql' => ['postgresql', 'postgres'],
        'mysql' => ['mysql'],
        'redis' => ['redis'],
        'docker' => ['docker'],
        'phpunit' => ['phpunit'],
        'supabase' => ['supabase'],
        'javascript' => ['javascript', 'js'],
        'typescript' => ['typescript', 'ts'],
        'tailwind' => ['tailwind', 'tailwindcss'],
        'api' => ['api', 'rest', 'restful'],
    ];

    /**
     * Adjacent skills worth verifying (configurable via
     * `lead-scout.skills.watch_list`): absent from the CV → potential.
     *
     * @var list<string>
     */
    public const array DEFAULT_WATCH_LIST = [
        'aws', 'forge', 'vapor', 'pest', 'filament', 'symfony', 'nestjs', 'next.js',
    ];

    /**
     * @param  list<string>  $watchList
     * @return array{confirmed: list<string>, potential: list<string>}
     */
    public static function split(string $cvBody, array $watchList): array
    {
        $confirmed = [];

        foreach (self::TERMS as $canonical => $synonyms) {
            foreach ($synonyms as $synonym) {
                if (self::mentions($cvBody, $synonym)) {
                    $confirmed[] = $canonical;

                    break;
                }
            }
        }

        $confirmedSet = array_flip($confirmed);
        $potential = [];

        foreach ($watchList as $term) {
            $term = strtolower(trim($term));

            if ($term !== '' && ! isset($confirmedSet[$term]) && ! self::mentions($cvBody, $term)) {
                $potential[] = $term;
            }
        }

        sort($confirmed);
        sort($potential);

        return ['confirmed' => $confirmed, 'potential' => $potential];
    }

    public static function mentions(string $body, string $term): bool
    {
        // Lookbehind excludes '.' so `js` never matches inside `vue.js`;
        // lookahead allows '.' so `PostgreSQL.` still matches at sentence end.
        return (bool) preg_match('/(?<![a-z0-9+#.])'.preg_quote($term, '/').'(?![a-z0-9+#])/i', $body);
    }

    /**
     * Every claimable term: taxonomy plus the watch list (an unconfirmed
     * AWS claim blocks `ready` exactly like an unconfirmed Vue claim).
     *
     * @param  list<string>  $watchList
     * @return list<string>
     */
    public static function claimTerms(array $watchList): array
    {
        return array_values(array_unique([
            ...array_keys(self::TERMS),
            ...array_map(static fn (string $term): string => strtolower(trim($term)), $watchList),
        ]));
    }

    /**
     * Capabilities a text claims that the profile has not confirmed
     * (spec US-5 CA-5): a draft making them cannot go `ready`.
     *
     * @param  list<string>  $confirmed
     * @param  list<string>  $watchList
     * @return list<string>
     */
    #[\NoDiscard]
    public static function unconfirmedClaims(string $text, array $confirmed, array $watchList): array
    {
        $confirmed = array_map(strtolower(...), $confirmed);

        return array_values(array_filter(
            self::claimTerms($watchList),
            static fn (string $term): bool => self::mentions($text, $term) && ! in_array($term, $confirmed, true),
        ));
    }
}
