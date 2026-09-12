<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Modules\VideoEdits\Domain\ValueObjects\AiAnalysis;

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

    public function forEdit(int $videoEditId): ?AiAnalysis;
}
