<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\Entities\Source;
use Modules\LeadScout\Domain\ValueObjects\RawPosting;

/**
 * One job-source adapter (RSS feed, employment API). Adapters declare
 * whether they serve a source row; the ingest handler picks the first
 * that supports it (open for new sources, closed for modification).
 */
interface JobSourcePort
{
    public function supports(Source $source): bool;

    /**
     * @return iterable<int, RawPosting>
     */
    public function fetchSince(Source $source, ?string $cursor): iterable;
}
