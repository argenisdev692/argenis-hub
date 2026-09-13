<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Generation;

/**
 * One video to write, with the run's choices (US-5, US-13, US-14).
 */
final readonly class GenerateVideoScriptCommand
{
    public function __construct(
        public int $courseId,
        public int $videoId,
        public string $writerProvider,
        public bool $withReview = false,
        public ?string $reviewerProvider = null,
        public ?int $runId = null,
        public bool $forcePractice = false,
        public ?string $feedbackNote = null,
        /** First generations auto-accept; regenerations wait for the author unless configured otherwise. */
        public bool $accept = true,
    ) {}
}
