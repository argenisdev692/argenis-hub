<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

/**
 * Starts and controls the background execution of a run (FR-16, FR-21, FR-22).
 * One batch wrapping one chain: course order plus progress and cancellation
 * (research R2.1).
 */
interface GenerationDispatcherPort
{
    /**
     * @param  list<int>  $videoIdsInOrder
     * @return string the batch id
     */
    public function dispatch(int $runId, int $courseId, array $videoIdsInOrder, bool $prepareFirst, string $writerProvider, ?string $feedbackNote = null): string;

    /**
     * Live progress 0–100, or null when the batch is gone.
     */
    public function progress(string $batchId): ?int;

    public function cancel(string $batchId): void;
}
