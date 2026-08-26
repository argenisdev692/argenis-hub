<?php

declare(strict_types=1);

namespace Modules\Company\Domain\ValueObjects;

use InvalidArgumentException;
use Stringable;
use Uri\Rfc3986\Uri;

/**
 * An absolute http(s) URL — the website and every social profile link.
 *
 * These values are rendered as `href` attributes on the public landing pages, so
 * the scheme allowlist is a security control, not tidiness: without it a stored
 * `javascript:…` or `data:…` value becomes stored XSS on every page that prints
 * the footer. A relative reference (`www.example.com`, which parses with a null
 * scheme and null host) is rejected for the same reason — the browser would
 * resolve it against the *visitor's* origin.
 *
 * Parsed with the PHP 8.5 URI extension rather than `parse_url()`, which is
 * lenient enough to accept strings no browser would.
 */
final readonly class WebUrl implements Stringable
{
    /** @var list<string> */
    private const array ALLOWED_SCHEMES = ['http', 'https'];

    private const int MAX_LENGTH = 255;

    public string $value;

    public function __construct(string $value)
    {
        $uri = Uri::parse(trim($value));

        if ($uri === null
            || ! in_array(mb_strtolower((string) $uri->getScheme()), self::ALLOWED_SCHEMES, true)
            || ($uri->getHost() ?? '') === ''
        ) {
            throw new InvalidArgumentException("[{$value}] is not an absolute http(s) URL.");
        }

        $normalized = $uri->toString();

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('A URL cannot exceed %d characters.', self::MAX_LENGTH),
            );
        }

        $this->value = $normalized;
    }

    /**
     * Build a URL, or `null` when the input is absent or blank.
     */
    public static function fromNullable(?string $value): ?self
    {
        return match (true) {
            $value === null, trim($value) === '' => null,
            default => new self($value),
        };
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
