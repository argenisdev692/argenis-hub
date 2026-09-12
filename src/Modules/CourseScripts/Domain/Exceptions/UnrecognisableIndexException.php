<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Exceptions;

use RuntimeException;

/**
 * The upload produced no usable course structure (spec FR-7).
 *
 * Raised instead of persisting an empty or half-formed course: a course with
 * zero videos cannot be generated from (FR-25) and would leave the author
 * debugging an empty screen rather than a rejected file.
 */
final class UnrecognisableIndexException extends RuntimeException
{
    public const string CODE = 'unrecognisable_index';

    /**
     * @param  list<string>  $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct('The uploaded file does not contain a recognisable course index.');
    }
}
