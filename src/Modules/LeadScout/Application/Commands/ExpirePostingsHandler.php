<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;

/**
 * Expires postings past the configured max age (spec US-2 CA-3, T027).
 * Expired postings keep their evidence but generate no leads and contribute
 * no active-vacancy signal.
 */
final readonly class ExpirePostingsHandler
{
    public function __construct(
        private JobPostingRepositoryPort $postings,
        private Config $config,
    ) {}

    public function handle(): int
    {
        $maxAgeDays = (int) $this->config->get('lead-scout.ingest.max_offer_age_days', 30);

        return $this->postings->expireActiveOlderThan(CarbonImmutable::now()->subDays($maxAgeDays));
    }
}
