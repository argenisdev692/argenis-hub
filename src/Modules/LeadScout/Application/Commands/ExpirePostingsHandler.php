<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Enums\PostingStatus;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutJobPostingEloquentModel;

/**
 * Expires postings past the configured max age (spec US-2 CA-3, T027).
 * Expired postings keep their evidence but generate no leads and contribute
 * no active-vacancy signal.
 */
final readonly class ExpirePostingsHandler
{
    public function __construct(private JobPostingRepositoryPort $postings) {}

    public function handle(): int
    {
        $cutoff = CarbonImmutable::now()->subDays((int) config('lead-scout.ingest.max_offer_age_days', 30));
        $expired = 0;

        ScoutJobPostingEloquentModel::query()
            ->where('status', PostingStatus::Active->value)
            ->where(function ($query) use ($cutoff): void {
                $query->where('published_at', '<', $cutoff)
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query->whereNull('published_at')->where('created_at', '<', $cutoff);
                    });
            })
            ->chunkById(200, function ($batch) use (&$expired): void {
                foreach ($batch as $posting) {
                    $this->postings->markExpired($posting->id);
                    $expired++;
                }
            });

        return $expired;
    }
}
