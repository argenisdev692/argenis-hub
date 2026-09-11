<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use DomainException;

/**
 * The worker does not have enough scratch disk for this edit (risk R5).
 */
final class InsufficientWorkspaceException extends DomainException implements PermanentVideoEditFailure
{
    public const string FAILURE_CODE = 'insufficient_workspace';

    public static function needs(int $requiredBytes, int $availableBytes): self
    {
        return new self(sprintf(
            'Not enough processing space: %d bytes needed, %d available.',
            $requiredBytes,
            $availableBytes,
        ));
    }

    public function failureCode(): string
    {
        return self::FAILURE_CODE;
    }

    public function failureDetails(): ?array
    {
        return null;
    }
}
