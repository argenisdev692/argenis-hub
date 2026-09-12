<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Modules\VideoEdits\Domain\Exceptions\AiAnalysisFailedException;
use Modules\VideoEdits\Domain\ValueObjects\AiAnalysis;
use Modules\VideoEdits\Domain\ValueObjects\ScriptDocument;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;

/**
 * Editorial analysis of a transcript against an optional script (V3 · US-12),
 * provider-neutral (EX-5).
 *
 * The contract takes a {@see Transcript} and NOT the video: the model's job is
 * judgement, and the transcript is what carries both the words and the exact
 * timings. Replacing Gemini with another provider must not change the
 * detectors, validation, render or reporting.
 *
 * `$instructions` is the user's own editorial prompt, passed as user content —
 * never as system rules (US-12). The returned {@see AiAnalysis} is the only
 * thing that can influence the edit, and every proposal in it is re-validated
 * downstream (EX-3), so a script that tries to talk its way past the rules
 * still cannot produce a cut outside the media.
 */
interface AiEditAnalysisPort
{
    /**
     * @throws AiAnalysisFailedException
     */
    public function analyze(
        Transcript $transcript,
        ?ScriptDocument $script,
        ?string $instructions,
        ?int $targetDurationMinutes,
    ): AiAnalysis;
}
