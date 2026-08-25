<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Docs;

use Shared\Domain\Ports\DocsVerificationPort;

/**
 * Thin transport contract over the two Context7 REST calls used by the
 * application (`GET /api/v2/libs/search` → `GET /api/v2/context`).
 *
 * Kept separate from {@see DocsVerificationPort} so the
 * Domain never learns that "resolve an id, then fetch snippets" is a
 * two-round-trip protocol. Callers that only need grounded snippets depend on
 * the Domain port; anything that genuinely needs a library id (diagnostics,
 * console commands) depends on this one.
 *
 * Both methods degrade to `null` / `[]` on any transport or provider failure.
 */
interface Context7ClientInterface
{
    /**
     * @return array{id: string, title: string}|null
     */
    public function resolveLibrary(string $libraryName, string $topicContext): ?array;

    /**
     * @return list<array{library: string, title: string, url: string, snippet: string}>
     */
    public function fetchContext(string $libraryId, string $topicContext): array;
}
