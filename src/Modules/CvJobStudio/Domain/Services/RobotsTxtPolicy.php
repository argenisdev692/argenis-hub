<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

use Uri\Rfc3986\Uri;

/**
 * Robots-conformance gate (FR-34, T-120): a fetcher asked for a path its
 * site disallows for `*` refuses before any request. Rules are injectable so
 * tests pin fixtures; production loads them from the fetched robots.txt.
 */
final readonly class RobotsTxtPolicy
{
    /** @param  array<string, list<string>>  $disallowedPathsByHost */
    public function __construct(private array $disallowedPathsByHost = []) {}

    #[\NoDiscard]
    public function mayFetch(string $url): bool
    {
        try {
            $uri = new Uri(trim($url));
        } catch (\Throwable) {
            return false;
        }

        $host = strtolower($uri->getHost() ?? '');
        $path = $uri->getPath() ?? '/';

        foreach ($this->disallowedPathsByHost[$host] ?? [] as $disallowed) {
            if ($disallowed === '/' || str_starts_with($path, $disallowed)) {
                return false;
            }
        }

        return true;
    }
}
