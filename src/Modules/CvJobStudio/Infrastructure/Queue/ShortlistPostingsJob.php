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
 * Pipeline stage 6 — D_disc shortlist. NOT YET IMPLEMENTED: P/K/R/F/N need
 * live discovery signals the providers do not feed yet (Slice 2), so this
 * stage ranks nothing and hands every text-less posting to extraction. Spend
 * is therefore bounded by the `extraction` budget in {@see ExtractPostingsJob},
 * not by a top-N cut. `DiscoveryScorer` holds the formula for when it lands.
 */
#[Tries(3)]
#[Timeout(120)]
#[Backoff([10, 60, 300])]
final class ShortlistPostingsJob implements ShouldQueue
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

        ExtractPostingsJob::dispatch($run->id, $this->userId);
    }
}
