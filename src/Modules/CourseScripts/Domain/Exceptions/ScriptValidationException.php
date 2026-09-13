<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Exceptions;

use RuntimeException;

/**
 * A draft still broke the deterministic gate after its bounded retries
 * (FR-44a). The video fails; nothing partial is presented as finished (US-5).
 */
final class ScriptValidationException extends RuntimeException
{
    public const string CODE = 'validation_failed';

    /**
     * @param  list<string>  $violations
     */
    public function __construct(public readonly string $step, public readonly array $violations)
    {
        parent::__construct(sprintf('The %s did not pass validation after retries.', $step));
    }
}
