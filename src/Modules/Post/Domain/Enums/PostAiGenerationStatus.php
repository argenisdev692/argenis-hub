<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Enums;

use Modules\Post\Application\Commands\GeneratePostContentHandler;

/**
 * The lifecycle of ONE background draft generation.
 *
 * Deliberately separate from {@see PostStatus}: that enum describes a piece of
 * published content, this one describes a job. `Failed` is not a thing a post
 * can be, and a post is never `Judging`.
 *
 * The four middle cases are the pipeline phases in
 * {@see GeneratePostContentHandler} and are
 * reported live so the wizard can show which one is running:
 *
 *   Researching → Writing → Judging   (repeats, up to MAX_ITERATIONS)
 *                                 ↓
 *                        GeneratingImage   (once, on the winner)
 *                                 ↓
 *                            Completed
 */
enum PostAiGenerationStatus: string
{
    /** Record created, not yet handed to the queue. */
    case Draft = 'draft';

    /** Accepted and dispatched; waiting for a worker to pick it up. */
    case Queued = 'queued';

    /** Gathering fresh Tavily context for the current iteration. */
    case Researching = 'researching';

    /** The writing model is drafting the current iteration. */
    case Writing = 'writing';

    /** An independent model is scoring the draft it was just handed. */
    case Judging = 'judging';

    /** The loop has picked its winner; the one billed cover render is running. */
    case GeneratingImage = 'generating_image';

    /** Finished. `result` holds the draft, whether or not every score passed. */
    case Completed = 'completed';

    /** Every iteration failed, or the worker died. `error_message` says which. */
    case Failed = 'failed';

    /**
     * Nothing more will happen on its own — the wizard stops polling here.
     */
    #[\NoDiscard]
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed => true,
            default => false,
        };
    }

    /**
     * Short present-tense line for the progress list. Kept beside the cases so
     * a new phase cannot be added without deciding what the user is told.
     */
    #[\NoDiscard]
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Preparing',
            self::Queued => 'Queued',
            self::Researching => 'Researching sources',
            self::Writing => 'Writing content',
            self::Judging => 'Quality review',
            self::GeneratingImage => 'Generating visual',
            self::Completed => 'Ready',
            self::Failed => 'Failed',
        };
    }
}
