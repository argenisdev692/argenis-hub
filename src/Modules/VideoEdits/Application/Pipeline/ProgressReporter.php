<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Pipeline;

use Closure;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;

/**
 * Turns per-stage progress into the single 0–100 value the client polls (D16, EX-7).
 *
 * Stage starts and the final 100 % are always written; in-stage updates are
 * throttled (minimum step AND minimum interval) so a 60-minute render does not
 * hammer the database. Progress never moves backwards.
 */
final class ProgressReporter
{
    private int $lastPercent = -1;

    private float $lastWriteAt = 0.0;

    /**
     * @param  Closure(): float  $clock  seconds, monotonic enough for throttling
     */
    public function __construct(
        private readonly VideoEditRepositoryPort $edits,
        private readonly string $videoEditUuid,
        private readonly int $minPercentStep,
        private readonly float $minIntervalSeconds,
        private readonly Closure $clock,
    ) {}

    public function startStage(ProcessingStage $stage): void
    {
        $this->write($stage->startPercent(), $stage, force: true);
    }

    public function advanceStage(ProcessingStage $stage, int $stagePercent): void
    {
        $withinStage = intdiv($stage->weight() * max(0, min(100, $stagePercent)), 100);

        $this->write($stage->startPercent() + $withinStage, $stage, force: false);
    }

    public function finish(): void
    {
        $this->write(100, ProcessingStage::Publish, force: true);
    }

    private function write(int $percent, ProcessingStage $stage, bool $force): void
    {
        $percent = max($percent, $this->lastPercent);
        $now = ($this->clock)();

        $tooSoon = $percent - $this->lastPercent < $this->minPercentStep
            || $now - $this->lastWriteAt < $this->minIntervalSeconds;

        if (! $force && $tooSoon) {
            return;
        }

        $this->edits->updateProgress($this->videoEditUuid, $percent, $stage);
        $this->lastPercent = $percent;
        $this->lastWriteAt = $now;
    }
}
