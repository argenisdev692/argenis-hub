<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\ValueObjects\FetchResult;

/**
 * Single-page fetch behind the cost ladder (spec FR-11). Implementations
 * never retry a `blocked` URL through another provider (FR-13).
 */
interface PageFetcherPort
{
    public function fetch(string $url): FetchResult;
}
