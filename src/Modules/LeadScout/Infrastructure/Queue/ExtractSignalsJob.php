<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;
use Modules\LeadScout\Application\Commands\ExtractSignalsHandler;
use Modules\LeadScout\Domain\Exceptions\BudgetExceededException;
use Modules\LeadScout\Domain\Ports\PipelineLoggerPort;

/**
 * Signal extraction (queue `lead-scout-llm`). LLM spend is rate-limited;
 * an exhausted AI budget ends the job quietly (free evidence stands).
 */
#[Queue('lead-scout-llm')]
#[Tries(3)]
#[Timeout(300)]
#[Backoff([60, 300])]
final class ExtractSignalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsPipelineFailure, SerializesModels;

    public function __construct(
        public readonly string $companyUuid,
        public readonly bool $extraRoundDone = false,
    ) {}

    /**
     * @return array{rules: int, ai: int, discarded: int, provider: ?string, model: ?string}
     */
    public function handle(ExtractSignalsHandler $extract, PipelineLoggerPort $logger): array
    {
        if (RateLimiter::tooManyAttempts('lead-scout-llm:extract', 10)) {
            $this->release(60);

            return ['rules' => 0, 'ai' => 0, 'discarded' => 0, 'provider' => null, 'model' => null];
        }

        RateLimiter::hit('lead-scout-llm:extract');

        try {
            $report = $extract->handle($this->companyUuid);
        } catch (BudgetExceededException $e) {
            $logger->pipeline('ai_budget_exhausted', ['company' => $this->companyUuid]);

            return ['rules' => 0, 'ai' => 0, 'discarded' => 0, 'provider' => null, 'model' => null];
        }

        ScoreCompanyJob::dispatch($this->companyUuid, null, $this->extraRoundDone);

        return $report;
    }
}
