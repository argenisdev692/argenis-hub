<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * Ordered processing stages with their share of the overall progress (EX-7).
 *
 * Case order IS execution order, and the progress bar never moves backwards, so
 * a stage placed before one that has already started would relabel the bar
 * without advancing it. `ProcessingStageTest` asserts the order holds.
 *
 * A stage that does not run for a given edit — no speech cleanup, no script —
 * is simply skipped, so progress moves faster rather than differently.
 */
enum ProcessingStage: string
{
    case Download = 'download';
    case Merge = 'merge';
    case Analysis = 'analysis';
    case AudioExtraction = 'audio_extraction';
    case Transcription = 'transcription';
    case SpeechDetection = 'speech_detection';
    case ScriptExtraction = 'script_extraction';
    case AiAnalysis = 'ai_analysis';
    case PlanCuts = 'plan_cuts';
    case Render = 'render';
    case Publish = 'publish';

    /**
     * Transcription and AI analysis carry real shares because both are network
     * round trips over a long recording — leaving either at 1 % would freeze
     * the bar exactly where users decide a job has hung.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Download => 5,
            self::Merge => 12,
            self::Analysis => 6,
            self::AudioExtraction => 3,
            self::Transcription => 13,
            self::SpeechDetection => 4,
            self::ScriptExtraction => 2,
            self::AiAnalysis => 10,
            self::PlanCuts => 2,
            self::Render => 38,
            self::Publish => 5,
        };
    }

    /**
     * Progress already reached when this stage starts.
     */
    public function startPercent(): int
    {
        $percent = 0;

        foreach (self::cases() as $stage) {
            if ($stage === $this) {
                break;
            }

            $percent += $stage->weight();
        }

        return $percent;
    }
}
