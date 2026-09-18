<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use Modules\CvJobStudio\Domain\Exceptions\BudgetExceededException;

interface SpendGuardPort
{
    /**
     * Pre-flight check run BEFORE the provider call (FR-33, SC-8). Throws
     * when the call would breach the period budget; records spend after.
     *
     * @throws BudgetExceededException
     */
    public function ensure(string $category, int $userId, ?int $runId = null): void;

    public function record(string $category, int $userId, string $provider, string $operation, int $costMicros, bool $succeeded, ?int $runId = null): void;
}
