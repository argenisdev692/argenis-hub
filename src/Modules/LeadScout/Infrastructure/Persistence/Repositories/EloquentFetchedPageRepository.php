<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Entities\FetchedPage;
use Modules\LeadScout\Domain\Enums\PageType;
use Modules\LeadScout\Domain\Ports\FetchedPageRepositoryPort;
use Modules\LeadScout\Infrastructure\Fetching\FormSummaryExtractor;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutFetchedPageEloquentModel;

final readonly class EloquentFetchedPageRepository implements FetchedPageRepositoryPort
{
    public function store(
        int $companyId,
        string $url,
        ?PageType $type,
        ?string $markdown,
        ?string $html,
        DateTimeImmutable $fetchedAt,
    ): void {
        $markdown = $markdown !== null && trim($markdown) !== '' ? $markdown : null;

        ScoutFetchedPageEloquentModel::query()->updateOrCreate(
            ['company_id' => $companyId, 'url' => mb_substr($url, 0, 2048)],
            [
                'page_type' => $type?->value,
                'content_markdown' => $markdown,
                'content_hash' => $markdown === null ? null : hash('sha256', $markdown),
                'forms_summary' => FormSummaryExtractor::summarize($html, $url),
                'fetched_at' => $fetchedAt,
            ],
        );
    }

    public function withContent(int $companyId): array
    {
        return ScoutFetchedPageEloquentModel::query()
            ->where('company_id', $companyId)
            ->whereNotNull('content_markdown')
            ->orderBy('id')
            ->get(['id', 'company_id', 'url', 'page_type', 'content_markdown', 'forms_summary', 'fetched_at'])
            ->map(static fn (ScoutFetchedPageEloquentModel $page): FetchedPage => new FetchedPage(
                id: $page->id,
                companyId: (int) $page->company_id,
                url: $page->url,
                pageType: $page->page_type,
                contentMarkdown: $page->content_markdown,
                formsSummary: $page->forms_summary,
                fetchedAt: $page->fetched_at?->toDateTimeImmutable(),
            ))
            ->values()
            ->all();
    }

    public function urlsFor(int $companyId): array
    {
        return ScoutFetchedPageEloquentModel::query()->where('company_id', $companyId)->pluck('url')->all();
    }

    public function countEvidencePages(int $companyId): int
    {
        return ScoutFetchedPageEloquentModel::query()
            ->where('company_id', $companyId)
            ->whereNotNull('content_markdown')
            ->whereNotNull('page_type')
            ->count();
    }

    public function pruneContentFetchedBefore(DateTimeImmutable $cutoff, DateTimeImmutable $prunedAt): int
    {
        return ScoutFetchedPageEloquentModel::query()
            ->whereNotNull('content_markdown')
            ->where('fetched_at', '<', $cutoff->format('Y-m-d H:i:s'))
            ->update(['content_markdown' => null, 'content_pruned_at' => $prunedAt->format('Y-m-d H:i:s')]);
    }
}
