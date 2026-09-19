<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

/**
 * Next steps of the discovery pipeline (plan §3.2): each call queues one
 * asynchronous stage for a company.
 */
interface PipelineQueuePort
{
    public function enrichCompany(string $companyUuid): void;

    public function extractSignals(string $companyUuid, bool $extraRound): void;

    public function scoreCompany(string $companyUuid, ?int $userId = null, bool $extraRoundDone = false): void;
}
