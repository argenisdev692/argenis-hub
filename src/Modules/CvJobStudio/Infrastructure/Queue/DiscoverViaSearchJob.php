<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;

/**
 * Pipeline stage 3 — Tavily gap-fill + unknown-employer discovery. NOT YET
 * IMPLEMENTED: a pass-through to stage 4 today. Tavily discovery runs in
 * stage 1 through `TavilySearchSource` (harvest registry).
 */
#[Tries(3)]
#[Timeout(120)]
#[Backoff([10, 60, 300])]
final class DiscoverViaSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, MarksRunFailed, Queueable, SerializesModels;

    public function __construct(public int $runId, public int $userId)
    {
        $this->queue = 'studio';
    }

    public function handle(): void
    {
        $run = StudioRunEloquentModel::query()->ownedBy($this->userId)->where('id', $this->runId)->first();

        if ($run === null) {
            return;
        }

        ResolveToSourceJob::dispatch($run->id, $this->userId);
    }
}
