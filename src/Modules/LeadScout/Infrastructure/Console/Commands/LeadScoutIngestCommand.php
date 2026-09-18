<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;
use Modules\LeadScout\Infrastructure\Queue\IngestSourceJob;

/**
 * Dispatches due active sources (spec US-2, T026). A source is due when
 * `last_run_at + frequency_minutes` has passed. Synchronous with
 * `--sync` for local debugging; queued otherwise.
 */
final class LeadScoutIngestCommand extends Command
{
    protected $signature = 'lead-scout:ingest {--source= : Source uuid to ingest} {--sync : Run inline instead of queuing}';

    protected $description = 'Ingest active job sources due for their frequency';

    public function handle(): int
    {
        $query = ScoutSourceEloquentModel::query()->where('status', 'active');

        if (is_string($this->option('source')) && $this->option('source') !== '') {
            $query->where('uuid', $this->option('source'));
        }

        $count = 0;

        foreach ($query->get() as $source) {
            if (! $this->isDue($source)) {
                continue;
            }

            if ((bool) $this->option('sync')) {
                IngestSourceJob::dispatchSync($source->uuid);
            } else {
                IngestSourceJob::dispatch($source->uuid);
            }

            $count++;
        }

        $this->info("Dispatched {$count} source(s).");

        return self::SUCCESS;
    }

    private function isDue(ScoutSourceEloquentModel $source): bool
    {
        if ($source->last_run_at === null) {
            return true;
        }

        return $source->last_run_at->addMinutes($source->frequency_minutes)->isPast();
    }
}
