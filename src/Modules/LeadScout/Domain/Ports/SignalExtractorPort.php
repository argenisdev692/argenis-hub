<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\Exceptions\BudgetExceededException;

/**
 * AI extraction of ambiguous signals (spec FR-6, FR-10). The prompt holds
 * scrubbed page text only; every excerpt returned is verified literally
 * against `$sourceText` before it counts.
 */
interface SignalExtractorPort
{
    /**
     * @return array{signals: list<array{signal_key: string, nature: string, excerpt: string, confidence: int, source_url: string}>, discarded: int, provider: string, model: string, company_type: ?string, team_size_observed: ?int}
     *
     * @throws BudgetExceededException
     */
    public function extract(string $prompt, string $sourceText, ?string $provider = null, ?string $model = null): array;
}
