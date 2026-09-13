<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Domain\Ports\GenerationRunRepositoryPort;
use Modules\CourseScripts\Domain\ValueObjects\CallUsage;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseGenerationRunEloquentModel;

/**
 * Adds provider calls to a run's counters (FR-24).
 */
final readonly class RecordRunUsageHandler
{
    public function __construct(
        private GenerationRunRepositoryPort $runs,
    ) {}

    public function handle(int $runId, CallUsage $usage): CourseGenerationRunEloquentModel
    {
        return $this->runs->addUsage($runId, $usage);
    }
}
