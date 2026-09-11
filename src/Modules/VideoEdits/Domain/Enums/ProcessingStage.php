<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * Ordered processing stages with their share of the overall progress (EX-7).
 *
 * Case order IS execution order. V2/V3 insert their stages (transcription,
 * script extraction, AI analysis) by adding cases and re-balancing weights;
 * the progress contract — one 0–100 value plus the current stage — is unchanged.
 */
enum ProcessingStage: string
{
    case Download = 'download';
    case Merge = 'merge';
    case Analysis = 'analysis';
    case PlanCuts = 'plan_cuts';
    case Render = 'render';
    case Publish = 'publish';

    public function weight(): int
    {
        return match ($this) {
            self::Download => 5,
            self::Merge => 15,
            self::Analysis => 10,
            self::PlanCuts => 5,
            self::Render => 60,
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
