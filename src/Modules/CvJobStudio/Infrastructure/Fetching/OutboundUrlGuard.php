<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Fetching;

use Psr\Http\Message\UriInterface;
use Uri\Rfc3986\Uri;

/**
 * SSRF guard (FR-34, OWASP A01:2025 / §15): every fetch target is checked
 * before the request, and {@see self::httpOptions()} re-validates every
 * redirect hop (a public URL 302-ing to 169.254.169.254 is refused — the
 * class of CVE-2026-32857). Non-http(s), never-fetch hosts (FR-50) and any
 * non-global address are denied.
 *
 * Addresses are judged with `FILTER_FLAG_GLOBAL_RANGE`, which covers the
 * private, loopback, link-local, CGNAT, `0.0.0.0/8`, unique-local and
 * IPv4-mapped IPv6 ranges in one flag — the hand-rolled CIDR list it replaces
 * let `[::1]`, `[::ffff:127.0.0.1]` and `0.0.0.0` through (the class of
 * CVE-2026-35409). Lives in Infrastructure because resolving a host is I/O.
 */
final readonly class OutboundUrlGuard
{
    private const int MAX_REDIRECTS = 3;

    /** @var \Closure(string): list<string> */
    private \Closure $resolver;

    /**
     * @param  list<string>  $neverFetchHosts  hosts excluded even when public (FR-50)
     * @param  (\Closure(string): list<string>)|null  $resolver  host → every A/AAAA address; injectable for tests
     */
    public function __construct(private array $neverFetchHosts = [], ?\Closure $resolver = null)
    {
        $this->resolver = $resolver ?? static fn (string $host): array => [
            ...(gethostbynamel($host) ?: []),
            ...array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
        ];
    }

    #[\NoDiscard]
    public function allowed(string $url): bool
    {
        try {
            $uri = new Uri(trim($url));
        } catch (\Throwable) {
            return false;
        }

        if (! in_array(strtolower($uri->getScheme() ?? ''), ['http', 'https'], true)) {
            return false;
        }

        $host = trim(strtolower($uri->getHost() ?? ''), '[]');

        if ($host === '' || $this->isNeverFetchHost($host)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isGlobalIp($host);
        }

        // An unresolvable host cannot be requested at all; the transport
        // re-resolves and the redirect hook re-checks every hop.
        foreach (($this->resolver)($host) as $ip) {
            if (! $this->isGlobalIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Guzzle options for `Http::withOptions()`: redirects stay http(s)-only,
     * capped, and each `Location` target passes {@see self::allowed()} before
     * it is requested.
     *
     * @return array{allow_redirects: array{max: int, strict: bool, protocols: list<string>, on_redirect: \Closure}}
     */
    #[\NoDiscard]
    public function httpOptions(): array
    {
        return [
            'allow_redirects' => [
                'max' => self::MAX_REDIRECTS,
                'strict' => true,
                'protocols' => ['http', 'https'],
                'on_redirect' => function (mixed $request, mixed $response, UriInterface $target): void {
                    if (! $this->allowed((string) $target)) {
                        throw new \RuntimeException('Redirect target refused by the outbound URL guard.');
                    }
                },
            ],
        ];
    }

    private function isNeverFetchHost(string $host): bool
    {
        foreach ($this->neverFetchHosts as $denied) {
            $denied = strtolower(trim($denied));

            if ($denied !== '' && ($host === $denied || str_ends_with($host, '.'.$denied))) {
                return true;
            }
        }

        return false;
    }

    private function isGlobalIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
    }
}
