<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Per-posting parallel scoring (one job per shortlisted posting). */
final class ScorePostingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $runId, public int $postingId, public int $userId)
    {
        $this->queue = 'studio';
    }

    public function handle(): void
    {
        // Scoring inputs come from the run's confirmed CV structure (Phase I);
        // the synchronous score/rescore endpoints cover explicit inputs today.
    }
}
