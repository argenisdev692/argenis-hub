<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Fetching;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Own robots.txt policy (spec D1, FR-13): `*` and own-agent groups,
 * Allow/Disallow with `*` and `$` wildcards, 24 h cache. A network
 * failure permits with a log line (fail-open is the crawlers' convention;
 * the per-site decision is re-checked on every ladder run, never stored
 * as a permanent pass).
 */
final readonly class RobotsTxtPolicy
{
    private const string CACHE_PREFIX = 'scout:robots:';

    public function __construct(private string $userAgent = 'LeadScoutBot') {}

    public function isAllowed(string $url): bool
    {
        if (preg_match('~^[a-z][a-z0-9+.-]*://([^/:?#]+)~i', trim($url), $m) !== 1) {
            return false;
        }

        $host = mb_strtolower($m[1]);
        $path = $this->pathOf($url);

        // Longest matching pattern wins (crawler convention); Allow wins ties.
        $best = null;

        foreach ($this->rulesFor($host) as $rule) {
            if (! $this->agentApplies($rule['agents'])) {
                continue;
            }

            if (! $this->pathMatches($rule['pattern'], $path)) {
                continue;
            }

            if ($best === null
                || mb_strlen($rule['pattern']) > mb_strlen($best['pattern'])
                || (mb_strlen($rule['pattern']) === mb_strlen($best['pattern']) && $rule['allow'])) {
                $best = $rule;
            }
        }

        return $best === null || $best['allow'];
    }

    private function pathOf(string $url): string
    {
        $path = (string) preg_replace('~^[a-z][a-z0-9+.-]*://[^/]+~i', '', trim($url));

        return $path === '' ? '/' : $path;
    }

    /**
     * @return array<int, array{agents: list<string>, allow: bool, pattern: string}>
     */
    private function rulesFor(string $host): array
    {
        return Cache::remember(self::CACHE_PREFIX.$host, now()->addHours(24), fn (): array => $this->download($host));
    }

    /**
     * @return array<int, array{agents: list<string>, allow: bool, pattern: string}>
     */
    private function download(string $host): array
    {
        try {
            $response = Http::timeout(5)->retry(1, 200)->get("https://{$host}/robots.txt");

            if ($response->failed()) {
                return [];
            }

            return self::parse($response->body());
        } catch (\Throwable $e) {
            Log::info('lead-scout.robots_unreachable', ['host' => $host, 'error' => mb_substr($e->getMessage(), 0, 200)]);

            return [];
        }
    }

    /**
     * @return array<int, array{agents: list<string>, allow: bool, pattern: string}>
     */
    public static function parse(string $body): array
    {
        $rules = [];
        $agents = [];
        $groupOpen = true;

        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map(trim(...), explode(':', $line, 2));
            $field = mb_strtolower($field);

            if ($field === 'user-agent') {
                if (! $groupOpen) {
                    $agents = [];
                    $groupOpen = true;
                }

                $agents[] = mb_strtolower($value);
            } elseif (($field === 'allow' || $field === 'disallow') && $agents !== []) {
                $groupOpen = false;

                if ($value === '') {
                    continue;
                }

                $rules[] = ['agents' => $agents, 'allow' => $field === 'allow', 'pattern' => $value];
            }
        }

        return $rules;
    }

    /**
     * @param  list<string>  $agents
     */
    private function agentApplies(array $agents): bool
    {
        $mine = mb_strtolower($this->userAgent);

        foreach ($agents as $agent) {
            if ($agent === '*' || str_contains($mine, $agent) || ($agent !== '' && str_contains($agent, '*') && fnmatch($agent, $mine))) {
                return true;
            }
        }

        return false;
    }

    private function pathMatches(string $pattern, string $path): bool
    {
        // Robots wildcards: `*` matches anything, trailing `$` anchors the
        // end. Everything else (including `?`) is literal.
        $anchored = str_ends_with($pattern, '$');
        $regex = '/^'.str_replace('\*', '.*', preg_quote(rtrim($pattern, '$'), '/')).($anchored ? '$' : '').'/';

        return preg_match($regex, $path) === 1;
    }
}
