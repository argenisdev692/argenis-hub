<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Log;
use Modules\VideoEdits\Application\Commands\MarkVideoEditFailedHandler;
use Modules\VideoEdits\Application\Commands\RunVideoEditHandler;
use Modules\VideoEdits\Domain\Exceptions\PermanentVideoEditFailure;
use Throwable;

/**
 * Runs one video edit on the dedicated `video-edits` Redis queue (AD-15).
 * Connection and queue name are set by the dispatcher from config, so tests use
 * `sync` while workers consume Upstash. `tags()` keeps it Horizon-ready.
 *
 * Tries 3 = the first attempt + 2 automatic retries (D13); the 3600 s timeout
 * stays below the connection's 3900 s `retry_after`. Permanent failures skip
 * the retries.
 */
#[Tries(3)]
#[Timeout(3600)]
#[Backoff([60, 300])]
final class ProcessVideoEditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $videoEditUuid,
    ) {}

    public function handle(RunVideoEditHandler $run): void
    {
        try {
            $run->handle($this->videoEditUuid, $this->attempts());
        } catch (PermanentVideoEditFailure $failure) {
            $this->fail($failure);
        }
    }

    public function failed(?Throwable $exception): void
    {
        // Class name only: messages may carry commands or local paths.
        Log::warning('video_edits.processing_failed', [
            'video_edit_uuid' => $this->videoEditUuid,
            'exception' => $exception === null ? null : $exception::class,
        ]);

        $failures = app(MarkVideoEditFailedHandler::class);

        if ($exception instanceof TimeoutExceededException) {
            $failures->handleTimeout($this->videoEditUuid);

            return;
        }

        $failures->handle($this->videoEditUuid, $exception);
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['video-edits', 'video-edit:'.$this->videoEditUuid];
    }
}
