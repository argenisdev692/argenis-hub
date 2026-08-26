<?php

declare(strict_types=1);

namespace Modules\Company\Domain\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * A normalized postal code.
 *
 * The edit form pre-fills this from the Google Places selection and keeps it
 * read-only by default, but the operator can unlock it — Places regularly
 * returns no postal code at all for a street-level match, and occasionally
 * returns one that belongs to a neighbouring district. Whichever way the value
 * arrives, it lands here, so normalization has to happen once, in one place:
 * trimmed, internal whitespace collapsed to a single space, upper-cased (Dutch
 * `1011 ab` and Canadian `k1a0b1` are conventionally written upper-case).
 *
 * Deliberately NOT format-validated per country. Postal-code grammars differ
 * wildly, several countries have none, and rejecting a code the operator can see
 * printed on their own utility bill is a worse failure than storing an odd one.
 */
final readonly class PostalCode implements Stringable
{
    private const int MAX_LENGTH = 20;

    public string $value;

    public function __construct(string $value)
    {
        $normalized = $value
            |> trim(...)
            |> (static fn (string $code): string => (string) preg_replace('/\s+/u', ' ', $code))
            |> mb_strtoupper(...);

        if ($normalized === '') {
            throw new InvalidArgumentException('A postal code cannot be blank.');
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('A postal code cannot exceed %d characters.', self::MAX_LENGTH),
            );
        }

        $this->value = $normalized;
    }

    /**
     * Build a postal code, or `null` when the input is absent or blank.
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
