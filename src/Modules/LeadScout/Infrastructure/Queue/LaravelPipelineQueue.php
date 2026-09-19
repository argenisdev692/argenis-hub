<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Queue;

use Modules\LeadScout\Domain\Ports\PipelineQueuePort;

final readonly class LaravelPipelineQueue implements PipelineQueuePort
{
    public function enrichCompany(string $companyUuid): void
    {
        EnrichCompanyJob::dispatch($companyUuid);
    }

    public function extractSignals(string $companyUuid, bool $extraRound): void
    {
        ExtractSignalsJob::dispatch($companyUuid, $extraRound);
    }

    public function scoreCompany(string $companyUuid, ?int $userId = null, bool $extraRoundDone = false): void
    {
        ScoreCompanyJob::dispatch($companyUuid, $userId, $extraRoundDone);
    }
}
