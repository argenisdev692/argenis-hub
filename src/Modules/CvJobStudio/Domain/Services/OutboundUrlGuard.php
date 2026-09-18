<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

use Uri\Rfc3986\Uri;

/**
 * SSRF guard (FR-34, OWASP §1/§15): every fetch target is checked before the
 * request, redirects are re-validated per hop. Private, link-local, cloud
 * metadata and non-http(s) targets are denied.
 */
final readonly class OutboundUrlGuard
{
    /** @var list<string> */
    private const array DENIED_CIDRS = [
        '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
        '169.254.0.0/16', '::1/128', 'fc00::/7', 'fe80::/10',
    ];

    /** @var list<string> */
    private const array DENIED_HOSTS = [];

    /**
     * @param  list<string>  $neverFetchHosts  hosts excluded even when public (FR-50)
     */
    public function __construct(private array $neverFetchHosts = []) {}

    #[\NoDiscard]
    public function allowed(string $url): bool
    {
        try {
            $uri = new Uri(trim($url));
        } catch (\Throwable) {
            return false;
        }

        $scheme = strtolower($uri->getScheme() ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($uri->getHost() ?? '');

        if ($host === '' || $this->isNeverFetchHost($host)) {
            return false;
        }

        $ip = gethostbyname($host);

        if ($ip !== $host && $this->isDeniedIp($ip)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false && $this->isDeniedIp($host)) {
            return false;
        }

        return true;
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

    private function isDeniedIp(string $ip): bool
    {
        foreach (self::DENIED_CIDRS as $cidr) {
            [$subnet, $bits] = explode('/', $cidr);

            if ($this->inCidr($ip, $subnet, (int) $bits)) {
                return true;
            }
        }

        return false;
    }

    private function inCidr(string $ip, string $subnet, int $bits): bool
    {
        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $bytes = (int) ceil($bits / 8);

        for ($i = 0; $i < $bytes; $i++) {
            $mask = $i === $bytes - 1 && $bits % 8 !== 0 ? (0xFF << (8 - $bits % 8)) & 0xFF : 0xFF;

            if ((ord($ipBinary[$i]) & $mask) !== (ord($subnetBinary[$i]) & $mask)) {
                return false;
            }
        }

        return true;
    }
}
