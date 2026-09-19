<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Queue;

use Illuminate\Support\Facades\Log;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;
use Throwable;

/**
 * Final-failure hook shared by every pipeline stage (OWASP A10:2025,
 * BACKEND-PHP §10). Without it a stage that exhausts its tries leaves the run
 * stuck in its last status forever, and the Runs page polls it indefinitely.
 * Logs the job and exception class only — never the message, which may echo
 * posting text or provider payloads.
 */
trait MarksRunFailed
{
    public function failed(?Throwable $exception): void
    {
        StudioRunEloquentModel::query()
            ->ownedBy($this->userId)
            ->where('id', $this->runId)
            ->update(['status' => 'failed', 'finished_at' => now()]);

        Log::warning('cv_studio.pipeline.job_failed', [
            'job' => class_basename(static::class),
            'run_id' => $this->runId,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}
