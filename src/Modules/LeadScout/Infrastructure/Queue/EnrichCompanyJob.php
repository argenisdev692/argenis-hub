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
use Modules\LeadScout\Application\Commands\EnrichCompanyHandler;

/**
 * Company enrichment (queue `lead-scout`). Idempotent — re-runs reuse
 * fresh cached pages and never duplicate stored rows.
 */
#[Queue('lead-scout')]
#[Tries(2)]
#[Timeout(600)]
#[Backoff([60, 300])]
final class EnrichCompanyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsPipelineFailure, SerializesModels;

    public function __construct(public readonly string $companyUuid) {}

    /**
     * @return array{status: string, pages: int}
     */
    public function handle(EnrichCompanyHandler $enrich): array
    {
        return $enrich->handle($this->companyUuid);
    }
}
