<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Research;

/**
 * Full-page retrieval of a public URL as Markdown. Sibling of
 * {@see TavilyClientInterface}: search finds the page, this reads it.
 */
interface FirecrawlClientInterface
{
    /**
     * Main-content Markdown of the page, or null when the key is empty, the
     * request fails or the breaker is open. Never throws.
     *
     * `$proxy` selects the Firecrawl proxy mode (`basic`, `auto`, `stealth`).
     * Null keeps the previous behaviour (no proxy param sent). Modules with a
     * basic-only rule (CvJobStudio T-159) pass `'basic'` explicitly.
     */
    public function scrape(string $url, ?string $proxy = null): ?string;
}
