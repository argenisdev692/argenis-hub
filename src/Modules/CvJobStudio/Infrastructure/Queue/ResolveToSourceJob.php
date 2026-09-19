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
 * Pipeline stage 4 — resolve listing-site signals to the employer's own copy.
 * NOT YET IMPLEMENTED in the pipeline: a pass-through to stage 5. Resolution
 * runs on demand per posting (`ResolvePostingSourceHandler`, POST resolve).
 */
#[Tries(3)]
#[Timeout(120)]
#[Backoff([10, 60, 300])]
final class ResolveToSourceJob implements ShouldQueue
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

        GatePostingsJob::dispatch($run->id, $this->userId);
    }
}
