<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\ValueObjects;

use Uri\Rfc3986\Uri;

/**
 * Canonical posting identity (FR-10). Drops the fragment, the seven tracking
 * params and the trailing slash so the same posting discovered on four boards
 * still collides on `url_hash`. Built on the PHP 8.5 URI extension, never on
 * the legacy global URL parser.
 */
final readonly class CanonicalPostingUrl
{
    /** @var list<string> */
    private const array TRACKING_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'];

    public string $value;

    public function __construct(string $url)
    {
        $this->value = self::canonicalize($url);
    }

    #[\NoDiscard]
    public static function canonicalize(string $url): string
    {
        $uri = new Uri(trim($url));

        $query = array_filter(
            explode('&', $uri->getQuery() ?? ''),
            static fn (string $pair): bool => $pair !== '' && ! in_array(strtolower(explode('=', $pair, 2)[0]), self::TRACKING_PARAMS, true),
        );

        $path = rtrim($uri->getPath() ?? '', '/');
        $port = $uri->getPort();

        $canonical = strtolower($uri->getScheme() ?? 'https').'://'.strtolower($uri->getHost() ?? '').($port !== null ? ':'.$port : '').$path;

        return $query === [] ? $canonical : $canonical.'?'.implode('&', $query);
    }

    #[\NoDiscard]
    public function hash(): string
    {
        return hash('sha256', $this->value);
    }
}
