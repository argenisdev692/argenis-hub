<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Exceptions;

use RuntimeException;

final class NothingGeneratedException extends RuntimeException
{
    public const string CODE = 'nothing_generated';

    public function __construct()
    {
        parent::__construct('There is nothing to download yet: generate at least one video first.');
    }
}
