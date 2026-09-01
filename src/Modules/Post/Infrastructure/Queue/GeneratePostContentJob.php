<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Queue;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Post\Application\Commands\GeneratePostContentHandler;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Domain\Enums\PostAiGenerationStatus;
use Modules\Post\Domain\Ports\PostAiGenerationRepositoryPort;
use Modules\Post\Domain\Services\PostContentQualityEvaluator;
use Modules\Post\Infrastructure\Broadcasting\PostGenerationProgressReporter;
use Throwable;

/**
 * The queued shell around {@see GeneratePostContentHandler}.
 *
 * It owns NO generation logic — the write/judge/pick/render loop lives in the
 * handler and is called from exactly one place, here. All this class does is
 * translate the handler's two possible outcomes into the terminal states the
 * wizard polls for: a draft on the row and `completed`, or a message and
 * `failed`. The phases in between are written by the pipeline itself through
 * {@see PostGenerationProgressReporter}.
 *
 * Why it is queued at all: the loop is up to
 * {@see PostContentQualityEvaluator::MAX_ITERATIONS} rounds of Tavily +
 * writing model + judging model, then one image render. That is minutes of
 * wall time. Holding an HTTP request open for it meant every generation raced
 * PHP's `max_execution_time` and the FastCGI read timeout, and a user who
 * closed the tab lost a run that had already been billed.
 *
 * Dependencies are method-injected on {@see self::handle()} (not constructor
 * promotion) — the job is serialized onto the queue, so only plain
 * scalars/DTOs belong on `$this`.
 */
#[Queue('default')]
#[Tries(1)]
#[Timeout(900)]
final class GeneratePostContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $generationUuid,
        private readonly GeneratePostContentData $data,
        private readonly ?int $causerId = null,
    ) {}

    public function handle(
        GeneratePostContentHandler $generate,
        PostAiGenerationRepositoryPort $generations,
    ): void {
        $generation = $generations->findByUuid($this->generationUuid);

        if ($generation === null) {
            Log::warning('post.ai.generation.record_missing', ['uuid' => $this->generationUuid]);

            return;
        }

        $causer = $this->causerId !== null ? User::find($this->causerId) : null;

        $generations->update($generation, ['started_at' => now()]);

        try {
            $draft = $generate->handle($this->generationUuid, $this->data, $causer);
        } catch (Throwable $exception) {
            Log::error('post.ai.generation.failed', [
                'uuid' => $this->generationUuid,
                'error' => $exception->getMessage(),
            ]);

            $this->markFailed($generations, $exception->getMessage());

            return;
        }

        // The handler already reported `completed` with its progress message;
        // this write adds the payload the wizard hands back to the form.
        DB::transaction(fn () => $generations->update($generation, [
            'status' => PostAiGenerationStatus::Completed->value,
            'progress' => 100,
            'iteration' => $draft->iterationsRequired,
            'result' => $draft->toArray(),
            'error_message' => null,
            'finished_at' => now(),
        ]));
    }

    /**
     * Terminal cleanup for a job the worker gave up on — a timeout kill, an
     * OOM, or an exception outside {@see self::handle()}'s own catch.
     *
     * Without this the row stays mid-phase forever: `Tries(1)` means there is
     * no retry to finish the work, and the wizard polls for a terminal state
     * that could never arrive, then gives up on its own timeout with nothing
     * to show. `failed` is the honest answer — the user can see what happened
     * and re-run.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('post.ai.generation.job_failed', [
            'uuid' => $this->generationUuid,
            'error' => $exception?->getMessage() ?? 'unknown',
        ]);

        $this->markFailed(
            app(PostAiGenerationRepositoryPort::class),
            'Generation did not finish (timeout or worker failure). Re-run it for this topic.',
        );
    }

    /**
     * Idempotent: a run that already reached a terminal state is left alone,
     * so a late `failed()` callback cannot overwrite a draft that in fact
     * completed.
     */
    private function markFailed(PostAiGenerationRepositoryPort $generations, string $message): void
    {
        $generation = $generations->findByUuid($this->generationUuid);

        if ($generation === null || $generation->status->isTerminal()) {
            return;
        }

        DB::transaction(fn () => $generations->update($generation, [
            'status' => PostAiGenerationStatus::Failed->value,
            'stage_message' => null,
            'error_message' => $message,
            'finished_at' => now(),
        ]));
    }
}
