<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Queue;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Log;
use Modules\CourseScripts\Application\Commands\ExecuteRunVideoHandler;
use Modules\CourseScripts\Domain\Enums\VideoOutcomeStatus;
use Modules\CourseScripts\Domain\Ports\GenerationRunRepositoryPort;
use Modules\CourseScripts\Domain\ValueObjects\CallUsage;
use Throwable;

/**
 * One video of a run, as a link of the run's chain (R2.1, R2.4).
 *
 * Tries 1: a retry is an explicit author action ("retry failed videos"),
 * never an automatic re-bill. The handler records failures itself and returns,
 * so the chain always continues; `failed()` only covers a worker dying (e.g.
 * timeout) before the handler could write the outcome.
 */
#[Tries(1)]
final class GenerateVideoScriptJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public bool $failOnTimeout = true;

    public int $timeout;

    public function __construct(
        public readonly int $runId,
        public readonly int $videoId,
        public readonly ?string $feedbackNote = null,
    ) {
        $this->timeout = (int) config('course-scripts.runs.job_timeout_seconds', 1800);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    public function handle(ExecuteRunVideoHandler $execute): void
    {
        if (! $execute->handle($this->runId, $this->videoId, $this->feedbackNote)) {
            $this->batch()?->cancel();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('course_scripts.video_job_failed', [
            'run_id' => $this->runId,
            'video_id' => $this->videoId,
            'exception' => $exception === null ? null : $exception::class,
        ]);

        $runs = app(GenerationRunRepositoryPort::class);
        $outcome = $runs->outcome($this->runId, $this->videoId);

        if ($outcome !== null && ! $outcome->status->isTerminal() && $outcome->status !== VideoOutcomeStatus::Pending) {
            $runs->failOutcome($this->runId, $this->videoId, 'worker_failed', CallUsage::none());
        }
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['course-scripts', 'run:'.$this->runId, 'video:'.$this->videoId];
    }
}
