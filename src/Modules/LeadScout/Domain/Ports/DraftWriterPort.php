<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\Exceptions\BudgetExceededException;

/**
 * AI writer of a draft's opening sentence only (spec US-5, T062).
 */
interface DraftWriterPort
{
    /**
     * @return array{opener: string, provider: string, model: string}
     *
     * @throws BudgetExceededException
     */
    public function write(string $prompt, ?string $provider = null, ?string $model = null): array;
}
