<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Tests\Support;

use Modules\CourseScripts\Domain\Ports\GenerationDispatcherPort;

/**
 * A dispatcher that queues nothing, for tests of a run while it is still
 * queued (cancel, one-active-run guard, status).
 */
final class RecordingDispatcher implements GenerationDispatcherPort
{
    /** @var list<array{run_id: int, video_ids: list<int>, prepare: bool, feedback: ?string}> */
    public array $dispatched = [];

    /** @var list<string> */
    public array $cancelled = [];

    /** @var list<array{course_id: int, provider: string}> */
    public array $prepared = [];

    public static function install(): self
    {
        $dispatcher = new self;
        app()->instance(GenerationDispatcherPort::class, $dispatcher);

        return $dispatcher;
    }

    public function dispatch(int $runId, int $courseId, array $videoIdsInOrder, bool $prepareFirst, string $writerProvider, ?string $feedbackNote = null): string
    {
        $this->dispatched[] = ['run_id' => $runId, 'video_ids' => $videoIdsInOrder, 'prepare' => $prepareFirst, 'feedback' => $feedbackNote];

        return 'batch-'.$runId;
    }

    public function prepare(int $courseId, string $writerProvider): void
    {
        $this->prepared[] = ['course_id' => $courseId, 'provider' => $writerProvider];
    }

    public function progress(string $batchId): ?int
    {
        return 40;
    }

    public function cancel(string $batchId): void
    {
        $this->cancelled[] = $batchId;
    }
}
