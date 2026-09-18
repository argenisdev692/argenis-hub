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
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;

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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $sourceUuid) {}

    #[\NoDiscard('Ingest counts must be captured')]
    public static function runningKey(string $sourceUuid): string
    {
        return "scout:source:running:{$sourceUuid}";
    }

    /**
     * @return array{created: int, linked: int, irrelevant: int, suppressed: int, expired: int}
     */
    public function handle(IngestSourceHandler $ingest): array
    {
        $source = ScoutSourceEloquentModel::query()->where('uuid', $this->sourceUuid)->first();

        if ($source === null) {
            return ['created' => 0, 'linked' => 0, 'irrelevant' => 0, 'suppressed' => 0, 'expired' => 0];
        }

        Cache::put(self::runningKey($source->uuid), true, now()->addMinutes(30));

        try {
            return $ingest->handle($source);
        } finally {
            Cache::forget(self::runningKey($source->uuid));
        }
    }
}
