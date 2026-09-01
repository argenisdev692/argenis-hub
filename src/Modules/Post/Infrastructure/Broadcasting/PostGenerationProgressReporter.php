<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Broadcasting;

use Modules\Post\Domain\Enums\PostAiGenerationStatus;
use Modules\Post\Domain\Ports\PostAiGenerationRepositoryPort;

/**
 * Single owner of "where is this background generation".
 *
 * Every phase of the loop reports through here, and here alone, so a phase is
 * recorded in exactly one shape twice: PERSISTED on the generation row (which
 * is what the wizard polls) and BROADCAST on the causer's private channel
 * (which is what a future Echo client will consume). Splitting those across
 * call sites is how the two would drift.
 *
 * {@see PostProgressNotifier} stays the pure broadcasting concern underneath —
 * it is still used on its own by the synchronous topics / social-copy / reel
 * flows, which have no row to write to.
 */
final readonly class PostGenerationProgressReporter
{
    public function __construct(
        private PostAiGenerationRepositoryPort $generations,
        private PostProgressNotifier $notifier,
    ) {}

    /**
     * `$generationUuid` is nullable so the content pipeline stays callable
     * outside a queued run — a test, or a future synchronous re-generate —
     * without a row having to exist. With no uuid this degrades to a plain
     * broadcast.
     */
    public function report(
        ?string $generationUuid,
        ?object $causer,
        PostAiGenerationStatus $status,
        string $message,
        int $progress,
        int $iteration = 0,
    ): void {
        if ($generationUuid !== null) {
            $this->generations->markProgress($generationUuid, $status->value, $message, $progress, $iteration);
        }

        $this->notifier->notify($causer, 'content', $status->value, $message, $progress);
    }
}
