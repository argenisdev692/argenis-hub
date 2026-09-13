<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Queue;

use Illuminate\Bus\Batch;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Bus;
use Modules\CourseScripts\Application\Commands\FinalizeGenerationRunHandler;
use Modules\CourseScripts\Domain\Ports\GenerationDispatcherPort;

/**
 * A run is ONE batch wrapping ONE chain (research R2.1): the chain gives course
 * order (FR-17), the batch gives progress and cancellation (FR-21/22), and
 * `allowFailures()` keeps one failure from cancelling the rest (R2.3).
 */
final readonly class QueuedGenerationDispatcher implements GenerationDispatcherPort
{
    public function __construct(
        private Config $config,
    ) {}

    public function dispatch(int $runId, int $courseId, array $videoIdsInOrder, bool $prepareFirst, string $writerProvider, ?string $feedbackNote = null): string
    {
        $chain = array_map(static fn (int $videoId): GenerateVideoScriptJob => new GenerateVideoScriptJob($runId, $videoId, $feedbackNote), $videoIdsInOrder);

        if ($prepareFirst) {
            array_unshift($chain, new PrepareCourseJob($courseId, $writerProvider, $runId));
        }

        $batch = Bus::batch([$chain])
            ->name('course-scripts:run:'.$runId)
            ->allowFailures()
            // Static, and only the id: batch callbacks are serialized.
            ->finally(static function (Batch $batch) use ($runId): void {
                app(FinalizeGenerationRunHandler::class)->handle($runId);
            });

        $connection = $this->config->get('course-scripts.runs.connection');

        if (is_string($connection) && $connection !== '') {
            $batch->onConnection($connection);
        }

        return $batch->onQueue((string) $this->config->get('course-scripts.runs.queue', 'default'))->dispatch()->id;
    }

    public function progress(string $batchId): ?int
    {
        return Bus::findBatch($batchId)?->progress();
    }

    public function cancel(string $batchId): void
    {
        Bus::findBatch($batchId)?->cancel();
    }
}
