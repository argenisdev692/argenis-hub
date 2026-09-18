<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Fetching;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Modules\LeadScout\Domain\Enums\FetchMethod;
use Modules\LeadScout\Domain\Enums\FetchStatus;
use Modules\LeadScout\Domain\Exceptions\BudgetExceededException;
use Modules\LeadScout\Domain\ValueObjects\FetchResult;
use Modules\LeadScout\Infrastructure\Budgets\BudgetLedger;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutFetchAttemptEloquentModel;

/**
 * Cost ladder (spec FR-11, T041): fresh cache → robots → HTTP direct →
 * Firecrawl (budgeted) → failure, recording every real attempt with the
 * company attached.
 *
 * A `blocked` answer is terminal — it is NEVER escalated to Firecrawl or
 * retried through another route (FR-13). Firecrawl only serves pages that
 * did not block but could not be read (empty SPA, transport failure).
 *
 * Placement note: the plan files this under Domain/Services, but it
 * orchestrates Cache, Robots, HTTP fetchers and the spend ledger, which
 * Domain may not import (layer rule). The pure decision table it replaces
 * would buy nothing here — the ladder IS the orchestration.
 */
final readonly class FetchLadder
{
    public function __construct(
        private RobotsTxtPolicy $robots,
        private OutboundUrlGuard $guard,
        private DirectHttpPageFetcher $direct,
        private FirecrawlPageFetcher $firecrawl,
        private BudgetLedger $budgets,
    ) {}

    public function fetch(ScoutCompanyEloquentModel $company, string $url): FetchResult
    {
        try {
            $this->guard->assertAllowed($url);
        } catch (InvalidArgumentException $e) {
            // Rejected without a single request (T041: linkedin → no-op).
            return new FetchResult($url, $url, FetchStatus::Failed, error: $e->getMessage());
        }

        $cached = $this->freshPage($company, $url);

        if ($cached !== null) {
            return $cached;
        }

        if (! $this->robots->isAllowed($url)) {
            $this->record($company, $url, FetchMethod::Robots, FetchStatus::SkippedRobots, 0, 0, 'robots disallow');

            return new FetchResult($url, $url, FetchStatus::SkippedRobots, error: 'robots disallow');
        }

        try {
            $this->budgets->ensure(BudgetCategory::Extraction, $this->firecrawlCost());
        } catch (BudgetExceededException $e) {
            return new FetchResult($url, $url, FetchStatus::Failed, error: $e->getMessage());
        }

        $started = microtime(true);
        $direct = $this->direct->fetch($url);
        $elapsed = (int) ((microtime(true) - $started) * 1000);

        if ($direct->status === FetchStatus::Blocked) {
            $this->record($company, $url, FetchMethod::Http, FetchStatus::Blocked, $elapsed, 0, $direct->error);

            return $direct;
        }

        if ($direct->succeeded()) {
            $this->record($company, $url, FetchMethod::Http, FetchStatus::Ok, $elapsed, 0, null);

            return $direct;
        }

        $reason = $direct->spaEmpty ? 'spa_empty' : ($direct->error ?? 'unreadable');

        if (! $direct->spaEmpty && $direct->status !== FetchStatus::Failed) {
            $this->record($company, $url, FetchMethod::Http, $direct->status, $elapsed, 0, $reason);

            return $direct;
        }

        $this->record($company, $url, FetchMethod::Http, FetchStatus::Failed, $elapsed, 0, $reason);

        return $this->viaFirecrawl($company, $url);
    }

    private function viaFirecrawl(ScoutCompanyEloquentModel $company, string $url): FetchResult
    {
        $cost = $this->firecrawlCost();

        try {
            $this->budgets->ensure(BudgetCategory::Extraction, $cost);
        } catch (BudgetExceededException $e) {
            return new FetchResult($url, $url, FetchStatus::Failed, error: $e->getMessage());
        }

        $started = microtime(true);
        $result = $this->firecrawl->fetch($url);
        $elapsed = (int) ((microtime(true) - $started) * 1000);

        // The call was billed even when the content is discarded.
        $this->budgets->spend(BudgetCategory::Extraction, $cost);
        $this->record($company, $url, FetchMethod::Firecrawl, $result->status, $elapsed, $cost, $result->error);

        return $result;
    }

    private function freshPage(ScoutCompanyEloquentModel $company, string $url): ?FetchResult
    {
        $maxAge = CarbonImmutable::now()->subDays((int) config('lead-scout.fetching.page_max_age_days', 14));

        $page = $company->fetchedPages()->where('url', $url)->orderByDesc('fetched_at')->first();

        if ($page === null || $page->content_markdown === null || trim($page->content_markdown) === '') {
            return null;
        }

        if ($page->fetched_at !== null && $page->fetched_at->lt($maxAge)) {
            return null;
        }

        return new FetchResult($url, $url, FetchStatus::Ok, markdown: $page->content_markdown);
    }

    private function firecrawlCost(): int
    {
        return (int) ((float) config('lead-scout.costs.firecrawl_scrape_eur', 0.01) * 1_000_000);
    }

    private function record(
        ScoutCompanyEloquentModel $company,
        string $url,
        FetchMethod $method,
        FetchStatus $status,
        int $durationMs,
        int $costMicros,
        ?string $error,
    ): void {
        ScoutFetchAttemptEloquentModel::query()->create([
            'company_id' => $company->id,
            'url' => mb_substr($url, 0, 2048),
            'method' => $method->value,
            'status' => $status->value,
            'duration_ms' => $durationMs,
            'cost_micros' => $costMicros,
            'error' => $error === null ? null : mb_substr($error, 0, 255),
        ]);
    }
}
