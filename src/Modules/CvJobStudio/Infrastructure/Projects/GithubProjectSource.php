<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Projects;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\ProjectSourcePort;

/**
 * Authenticated user's GitHub repositories as CV project entries (T-008
 * shape): the token is read from `config/services.php` and never logged —
 * only repo names/descriptions/URLs leave this class. No token → no calls,
 * never an error. Forks are skipped (portfolio shows own work), capped at 50
 * recently-updated repos.
 *
 * Single-tenant by design: ONE global token (`services.github.token`) serves
 * every caller, so `$userId` is accepted for the {@see ProjectSourcePort}
 * contract but intentionally ignored — in a multi-user deployment all users
 * would see the same repositories. Per-user tokens (OAuth device flow,
 * per-user encrypted vault column) are the documented upgrade path when that
 * stops being true.
 */
final readonly class GithubProjectSource implements ProjectSourcePort
{
    private const int LIMIT = 50;

    /** @return list<array{title: string, description: string|null, url: string|null}> */
    public function projectsForUser(int $userId): array
    {
        $token = (string) config('services.github.token', '');

        if ($token === '') {
            return [];
        }

        try {
            $response = Http::withToken($token)
                ->accept('application/vnd.github+json')
                ->timeout(5)
                ->retry(1, 500)
                ->get('https://api.github.com/user/repos', [
                    'per_page' => self::LIMIT,
                    'sort' => 'updated',
                    'direction' => 'desc',
                ]);
        } catch (\Throwable) {
            return [];
        }

        if ($response->failed()) {
            return [];
        }

        $projects = [];

        foreach ((array) $response->json() as $repo) {
            if (! is_array($repo) || ($repo['fork'] ?? false) === true) {
                continue;
            }

            $name = trim((string) ($repo['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $projects[] = [
                'title' => $name,
                'description' => isset($repo['description']) ? (string) $repo['description'] : null,
                'url' => isset($repo['html_url']) ? (string) $repo['html_url'] : null,
            ];

            if (count($projects) >= self::LIMIT) {
                break;
            }
        }

        return $projects;
    }
}
