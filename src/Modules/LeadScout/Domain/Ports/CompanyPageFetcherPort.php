<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\ValueObjects\FetchResult;

/**
 * Cost-ordered page retrieval for a company (spec FR-11, FR-13): fresh
 * cache → robots → direct HTTP → extraction service. A block is an answer,
 * never an obstacle to route around. Every attempt is recorded.
 */
interface CompanyPageFetcherPort
{
    public function fetch(int $companyId, string $url): FetchResult;
}
