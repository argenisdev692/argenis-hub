<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Queue;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Log;
use Modules\CourseScripts\Application\Commands\PrepareCourseHandler;
use Modules\CourseScripts\Application\Commands\RecordRunUsageHandler;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Throwable;

/**
 * Bible proposal + subject research, queued (plan §3.3). Inside a run it is the
 * first link of the chain and its calls count toward the run; standalone it is
 * the author's "prepare" action.
 *
 * It never throws: a failed bible proposal must not halt the chain — the
 * video jobs that follow simply write without one.
 */
#[Tries(1)]
#[Timeout(300)]
final class PrepareCourseJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly int $courseId,
        public readonly string $writerProvider,
        public readonly ?int $runId = null,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    public function handle(PrepareCourseHandler $prepare, CourseRepositoryPort $courses, RecordRunUsageHandler $usage): void
    {
        $course = $courses->findById($this->courseId);

        if ($course === null) {
            return;
        }

        try {
            $calls = $prepare->handle($course, $this->writerProvider);

            if ($this->runId !== null) {
                $usage->handle($this->runId, $calls);
            }
        } catch (Throwable $exception) {
            Log::warning('course_scripts.preparation_failed', [
                'course_id' => $this->courseId,
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['course-scripts', 'course:'.$this->courseId];
    }
}
