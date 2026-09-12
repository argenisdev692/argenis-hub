<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use Closure;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;

/**
 * Everything a cut-decision producer may look at: the requested mode and
 * parameters plus the merged working file (EX-2). V2 speech producers and the V3
 * AI producer read the same context; they never touch persistence or rendering.
 */
final readonly class DecisionContext
{
    /**
     * `$ownerId` and `$sourceFingerprints` are what a producer needs to reuse
     * expensive per-content work instead of redoing it (V2 transcript reuse,
     * US-11; the same key serves V3). They are optional so a producer that only
     * looks at the working file — every V1 producer — is unaffected.
     *
     * @param  array<string, mixed>  $parameters
     * @param  list<string>  $sourceFingerprints  per-source SHA-256, in source order (EX-6)
     */
    public function __construct(
        public VideoEditMode $mode,
        public array $parameters,
        public string $workingPath,
        public MediaProbe $workingProbe,
        public ?int $videoEditId = null,
        public ?string $videoEditUuid = null,
        public ?int $ownerId = null,
        public array $sourceFingerprints = [],
        public ?Closure $onStageStart = null,
    ) {}

    /**
     * Lets a producer own its own stages on the progress bar (EX-7).
     *
     * Without this a V2 edit would sit at the analysis percentage for the whole
     * of a multi-minute transcription — the exact point at which users decide a
     * job has hung. The pipeline stays ignorant of which stages a producer has;
     * it just forwards them to the reporter.
     */
    public function enterStage(ProcessingStage $stage): void
    {
        if ($this->onStageStart !== null) {
            ($this->onStageStart)($stage);
        }
    }

    /**
     * One key for "these recordings, in this order" (R5). Order is part of the
     * hash because the same clips joined the other way round are a different
     * timeline, and every transcript timestamp would be wrong.
     */
    #[\NoDiscard]
    public function contentKey(): string
    {
        return hash('sha256', implode('|', $this->sourceFingerprints));
    }
}
