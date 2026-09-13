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
     */
    public function scrape(string $url): ?string;
}
