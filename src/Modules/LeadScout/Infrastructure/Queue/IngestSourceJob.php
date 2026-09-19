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
use Illuminate\Support\Facades\Cache;
use Modules\LeadScout\Application\Commands\IngestSourceHandler;

/**
 * Async source ingest (queue `lead-scout`). Idempotent per run; the
 * `scout:source:running:{uuid}` marker lets `POST sources/{uuid}/run`
 * answer 409 while a run is in flight (T028).
 */
#[Queue('lead-scout')]
#[Tries(3)]
#[Timeout(300)]
#[Backoff([30, 120, 300])]
final class IngestSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsPipelineFailure, SerializesModels;

    public function __construct(public readonly string $sourceUuid) {}

    #[\NoDiscard('The running-marker key must be used')]
    public static function runningKey(string $sourceUuid): string
    {
        return "scout:source:running:{$sourceUuid}";
    }

    /**
     * @return array{created: int, linked: int, irrelevant: int, suppressed: int, expired: int}
     */
    public function handle(IngestSourceHandler $ingest): array
    {
        Cache::put(self::runningKey($this->sourceUuid), true, now()->addMinutes(30));

        try {
            return $ingest->handle($this->sourceUuid);
        } finally {
            Cache::forget(self::runningKey($this->sourceUuid));
        }
    }
}
