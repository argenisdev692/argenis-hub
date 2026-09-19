<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\PageType;

/**
 * A public company page kept as evidence (spec FR-11). Markdown is pruned
 * after 30 days; the forms summary never contains submitted data.
 */
final readonly class FetchedPage
{
    /**
     * @param  array<string, mixed>|null  $formsSummary
     */
    public function __construct(
        public int $id,
        public int $companyId,
        public string $url,
        public ?PageType $pageType,
        public ?string $contentMarkdown,
        public ?array $formsSummary,
        public ?DateTimeImmutable $fetchedAt,
    ) {}
}
