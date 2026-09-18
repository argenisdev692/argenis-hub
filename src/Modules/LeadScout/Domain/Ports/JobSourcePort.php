<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Application\DTOs\RawPostingData;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;

/**
 * One job-source adapter (RSS feed, employment API). Adapters declare
 * whether they serve a source row; the ingest handler picks the first
 * that supports it (open for new sources, closed for modification).
 */
interface JobSourcePort
{
    public function supports(ScoutSourceEloquentModel $source): bool;

    /**
     * @return iterable<int, RawPostingData>
     */
    public function fetchSince(ScoutSourceEloquentModel $source, ?string $cursor): iterable;
}
