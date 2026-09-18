<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Fetching;

use InvalidArgumentException;

/**
 * Outbound-URL safety (OWASP §15, spec FR-13/FR-25): http/https only, no
 * private or metadata ranges, no professional networks, no contact-data
 * brokers, no restrictive portals. DNS resolves through an injectable
 * resolver so unit tests never touch the network.
 */
final readonly class OutboundUrlGuard
{
    /**
     * @var list<string>
     */
    private const array DENY_HOSTS = [
        'linkedin.com', 'xing.com', 'indeed.com', 'glassdoor.com',
        'apollo.io', 'zoominfo.com', 'rocketreach.co', 'lusha.com',
        'kaspr.io', 'hunter.io',
    ];

    /**
     * @var list<string>
     */
    private const array DENY_KEYWORDS = [
        'linkedin', 'xing', 'apollo', 'zoominfo', 'rocketreach', 'lusha', 'kaspr',
    ];

    /**
     * @var \Closure(string): list<string>
     */
    private readonly \Closure $resolver;

    /**
     * @param  (\Closure(string): list<string>)|null  $resolver
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver instanceof \Closure
            ? $resolver
            : static fn (string $host): array => gethostbynamel($host) ?: [];
    }

    /**
     * @throws InvalidArgumentException with the rejection reason
     */
    public function assertAllowed(string $url): void
    {
        // Deliberate string surgery, never parse_url() (BACKEND-PHP §3.1).
        if (preg_match('~^([a-z][a-z0-9+.-]*)://([^/:?#@]+)~i', trim($url), $m) !== 1) {
            throw new InvalidArgumentException("URL has no valid scheme/host: {$url}.");
        }

        $scheme = mb_strtolower($m[1]);
        $host = mb_strtolower($m[2]);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("Only http/https URLs are allowed: {$url}.");
        }

        $bare = (string) preg_replace('/^www\./', '', $host);

        foreach ([...self::DENY_HOSTS, ...(array) config('lead-scout.result_domain_denylist', [])] as $denied) {
            $denied = mb_strtolower(trim((string) $denied));

            if ($denied !== '' && ($bare === $denied || str_ends_with($bare, '.'.$denied))) {
                throw new InvalidArgumentException("Denied host: {$host} (networks, brokers and directories are never fetched).");
            }
        }

        foreach (self::DENY_KEYWORDS as $keyword) {
            if (str_contains($bare, $keyword)) {
                throw new InvalidArgumentException("Denied host keyword: {$host}.");
            }
        }

        if (filter_var($bare, FILTER_VALIDATE_IP) !== false) {
            $this->assertPublicIp($bare, $url);
        } else {
            foreach (($this->resolver)($bare) as $ip) {
                $this->assertPublicIp($ip, $url);
            }
        }
    }

    public function allows(string $url): bool
    {
        try {
            $this->assertAllowed($url);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertPublicIp(string $ip, string $url): void
    {
        $private = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;

        $metaV4 = str_starts_with($ip, '169.254.');
        $metaV6 = $ip === '::1' || str_starts_with(mb_strtolower($ip), 'fc') || str_starts_with(mb_strtolower($ip), 'fe80');

        if ($private || $metaV4 || $metaV6) {
            throw new InvalidArgumentException("Private/metadata IPs are never fetched: {$url}.");
        }
    }
}
