<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

/**
 * Canonical company host (spec FR-4): lowercase, no `www.`, IDN → punycode,
 * no scheme/path/query/port. Subdomains other than `www.` are PRESERVED —
 * `careers.example.com` is usually a third-party ATS, not the company site,
 * so stripping it would misattribute the company (US-3 CA-4 merges those as
 * aliases at the company level, never here).
 *
 * Host extraction is deliberate string surgery, not `parse_url()` (banned by
 * BACKEND-PHP §3.1) and not `ext-uri` (which rejects non-ASCII hosts before
 * IDN conversion can run).
 */
final class CanonicalDomain
{
    public function __construct(
        public private(set) string $value {
            get => $this->value;
            set {
                $normalized = strtolower(trim($value));

                if ($normalized === '') {
                    throw new \InvalidArgumentException('Domain must not be empty.');
                }

                $ascii = idn_to_ascii($normalized, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

                if ($ascii === false) {
                    throw new \InvalidArgumentException("Domain is not valid IDN: {$value}.");
                }

                if (! preg_match('/^(?!-)([a-z0-9-]{1,63}\.)+[a-z]{2,63}$/', $ascii)) {
                    throw new \InvalidArgumentException("Domain is not a valid host: {$value}.");
                }

                $this->value = $ascii;
            }
        }
    ) {}

    /**
     * @throws \InvalidArgumentException when no valid host can be extracted
     */
    public static function fromUrl(string $url): self
    {
        $url = trim($url);

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            throw new \InvalidArgumentException("URL has no scheme: {$url}.");
        }

        $authority = explode('/', (string) preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $url), 2)[0];
        $authority = explode('@', $authority);
        $host = (string) array_last($authority);
        $host = explode(':', $host)[0];

        if ($host === '') {
            throw new \InvalidArgumentException("URL has no host: {$url}.");
        }

        $host = preg_replace('/^www\./', '', strtolower($host)) ?? $host;

        return new self($host);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
