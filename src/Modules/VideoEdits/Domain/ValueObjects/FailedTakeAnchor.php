<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Where an abandoned attempt ended: the point the speaker restarted from.
 *
 * Two independent signals produce one, and {@see FailedTakeDetector} treats them
 * identically from then on. The two positions it carries are not the same thing and
 * confusing them is what makes the restart signal useless:
 *
 *  - `takeEndsBeforeMs` is where the restart or the marker begins. The take ends on
 *    the word before it.
 *  - `seekFromMs` is where the walk back starts.
 *
 * For a marker they coincide. For a restart they do not: the repeat proves where
 * the failed attempt *ended* and that its first copy *began*, so the walk starts
 * from the beginning of that first copy rather than from the restart — otherwise a
 * ceiling meant as a guard would truncate a take whose extent is already known,
 * and this module's own recordings contain a nine-second abandoned attempt.
 */
final readonly class FailedTakeAnchor
{
    public function __construct(
        public int $takeEndsBeforeMs,
        public int $seekFromMs,
        public bool $corroborated = false,
        public FailedTakeAnchorKind $kind = FailedTakeAnchorKind::PauseMarker,
    ) {
        if ($takeEndsBeforeMs < 0 || $seekFromMs < 0) {
            throw new InvalidArgumentException('A failed take anchor needs non-negative positions.');
        }
    }

    /**
     * A spoken marker: the take is what came before the marker, so both positions
     * are the marker's own start.
     */
    public static function pauseMarker(int $startMs): self
    {
        return new self(takeEndsBeforeMs: $startMs, seekFromMs: $startMs, kind: FailedTakeAnchorKind::PauseMarker);
    }

    /**
     * A repeated clause. The failed attempt began at the first copy's start, which
     * is measured rather than guessed.
     */
    public static function restart(Restart $restart, bool $corroborated = false): self
    {
        return new self(
            takeEndsBeforeMs: $restart->restartStartMs,
            seekFromMs: $restart->failedStartMs,
            corroborated: $corroborated,
            kind: FailedTakeAnchorKind::Restart,
        );
    }
}
