<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

use Modules\LeadScout\Domain\Enums\FetchStatus;

/**
 * One fetch outcome. `blocked` is terminal (never escalated); `spaEmpty`
 * and transport failures are the only Firecrawl-eligible cases (FR-13).
 */
final readonly class FetchResult
{
    public function __construct(
        public string $url,
        public string $finalUrl,
        public FetchStatus $status,
        public ?string $markdown = null,
        public ?string $html = null,
        public ?string $error = null,
        public bool $spaEmpty = false,
    ) {}

    public function succeeded(): bool
    {
        return $this->status === FetchStatus::Ok && $this->markdown !== null && trim($this->markdown) !== '';
    }
}
