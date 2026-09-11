<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use DomainException;

/**
 * Corrected ranges were sent with a retry whose failure had nothing to do with
 * the ranges (HTTP 422 · P1).
 */
final class ManualRangesNotCorrectableException extends DomainException
{
    public const string CODE = 'ranges_not_correctable';

    public static function create(): self
    {
        return new self('Ranges can only be corrected when the edit failed because of them.');
    }
}
