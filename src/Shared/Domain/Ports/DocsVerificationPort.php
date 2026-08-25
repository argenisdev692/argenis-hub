<?php

declare(strict_types=1);

namespace Shared\Domain\Ports;

use Shared\Infrastructure\Docs\Context7DocsAdapter;
use Shared\Infrastructure\Research\TavilyClientInterface;

/**
 * Grounds AI-generated technical content in the CURRENT official documentation
 * of a library/framework, complementing the broad web research served by
 * {@see TavilyClientInterface}.
 *
 * Domain-pure: only native types, no transport concepts. The bound adapter
 * ({@see Context7DocsAdapter}) MUST degrade
 * gracefully — an unreachable provider, a missing API key or an unknown
 * library returns an EMPTY array and never throws, so a long content
 * generation run is never aborted by a docs lookup (spec FR-15).
 */
interface DocsVerificationPort
{
    /**
     * @param  string  $libraryName  Human name of the library ("laravel", "react", "github copilot").
     * @param  string  $topicContext  Natural-language question/topic the snippets must answer.
     * @return list<array{library: string, title: string, url: string, snippet: string}>
     */
    public function lookup(string $libraryName, string $topicContext): array;
}
