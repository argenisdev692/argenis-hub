<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Generation;

use Modules\CourseScripts\Domain\ValueObjects\CallUsage;

final readonly class GeneratedScript
{
    public function __construct(
        public int $scriptVersionId,
        public CallUsage $usage,
        public int $reviewIterations,
    ) {}
}
