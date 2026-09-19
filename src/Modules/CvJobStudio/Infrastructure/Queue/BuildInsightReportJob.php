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
use Modules\CvJobStudio\Application\Commands\BuildInsightReportHandler;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;

/** Final stage: always runs, even on zero matches (SC-1). */
#[Tries(3)]
#[Timeout(120)]
#[Backoff([10, 60, 300])]
final class BuildInsightReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, MarksRunFailed, Queueable, SerializesModels;

    public function __construct(public int $runId, public int $userId)
    {
        $this->queue = 'studio';
    }

    public function handle(BuildInsightReportHandler $reports): void
    {
        $run = StudioRunEloquentModel::query()->ownedBy($this->userId)->where('id', $this->runId)->first();

        if ($run === null) {
            return;
        }

        (void) $reports->handle($run->uuid, $this->userId);

        $run->update(['status' => 'finished', 'finished_at' => now()]);
    }
}
