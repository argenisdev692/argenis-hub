<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Modules\VideoEdits\Domain\ValueObjects\AiAnalysis;
use Modules\VideoEdits\Domain\ValueObjects\DurationPlan;

/**
 * Persistence for the advisory half of an AI edit (US-14).
 *
 * Stores ONLY the validated recommendations and the conclusion — never the raw
 * provider response (decision R10). The cut decisions themselves, applied and
 * rejected alike, already persist through the normal decision tables, so the
 * report is reconstructable from stored data without reprocessing the video
 * (EX-8) and a hard delete removes all of it.
 */
interface AiReportStorePort
{
    public function store(int $videoEditId, AiAnalysis $analysis): void;

    /**
     * The duration plan is measured by the pipeline, after every producer has
     * spoken and before the owner is asked to approve anything — it therefore
     * cannot travel with the model's analysis, which is stored earlier.
     */
    public function storeDurationPlan(int $videoEditId, ?DurationPlan $plan): void;

    public function forEdit(int $videoEditId): ?AiAnalysis;
}
