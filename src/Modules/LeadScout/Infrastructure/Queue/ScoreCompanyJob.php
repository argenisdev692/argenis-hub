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
use Modules\LeadScout\Application\Commands\ExtractDecisionMakersHandler;
use Modules\LeadScout\Application\Commands\ScoreCompanyHandler;

/**
 * Async re-score (queue `lead-scout`). Idempotent — re-running only
 * recomputes the deterministic result.
 */
#[Queue('lead-scout')]
#[Tries(3)]
#[Timeout(120)]
#[Backoff([10, 60, 300])]
final class ScoreCompanyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsPipelineFailure, SerializesModels;

    public function __construct(
        public readonly string $companyUuid,
        public readonly ?int $userId = null,
        public readonly bool $extraRoundDone = false,
    ) {}

    public function handle(ScoreCompanyHandler $score, ExtractDecisionMakersHandler $decisors): void
    {
        (void) $score->handle($this->companyUuid, $this->userId, $this->extraRoundDone);
        (void) $decisors->persistIfTierAB($this->companyUuid);
    }
}
