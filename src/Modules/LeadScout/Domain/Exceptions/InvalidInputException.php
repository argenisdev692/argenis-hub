<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * A use-case rejected its input field by field. The provider maps it to the
 * framework's 422 validation response, so handlers stay framework-free
 * while clients keep the usual `errors.{field}` shape.
 */
final class InvalidInputException extends RuntimeException
{
    /**
     * @param  array<string, string|list<string>>  $errors
     */
    private function __construct(public readonly array $errors)
    {
        parent::__construct((string) (array_first(array_map(
            static fn (string|array $message): string => is_array($message) ? (string) array_first($message) : $message,
            $errors,
        )) ?? 'The given data was invalid.'));
    }

    /**
     * @param  array<string, string|list<string>>  $errors
     */
    public static function withMessages(array $errors): self
    {
        return new self($errors);
    }
}
