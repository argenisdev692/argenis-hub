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

    public function progress(string $batchId): ?int
    {
        return 40;
    }

    public function cancel(string $batchId): void
    {
        $this->cancelled[] = $batchId;
    }
}
