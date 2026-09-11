<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\VideoEdits\Application\Pipeline\DecisionProducerRegistry;
use Modules\VideoEdits\Application\Pipeline\Producers\ManualRangeDecisionProducer;
use Modules\VideoEdits\Application\Pipeline\Producers\SilenceDecisionProducer;
use Modules\VideoEdits\Domain\Exceptions\InvalidCutRangesException;
use Modules\VideoEdits\Domain\Exceptions\ManualRangesNotCorrectableException;
use Modules\VideoEdits\Domain\Exceptions\SourceUploadInvalidException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditStateConflictException;
use Modules\VideoEdits\Domain\Ports\VideoEditProcessingDispatcherPort;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Domain\Ports\VideoEditWorkspacePort;
use Modules\VideoEdits\Domain\Services\CutPlanner;
use Modules\VideoEdits\Infrastructure\Console\Commands\PurgeExpiredVideoEditSourcesCommand;
use Modules\VideoEdits\Infrastructure\Console\Commands\SweepStaleVideoEditsCommand;
use Modules\VideoEdits\Infrastructure\Media\LocalVideoEditWorkspace;
use Modules\VideoEdits\Infrastructure\Persistence\Repositories\EloquentVideoEditRepository;
use Modules\VideoEdits\Infrastructure\Queue\QueuedVideoEditProcessingDispatcher;

/**
 * Composition root of the Video Edits module (spec 001-video-edit).
 *
 * V1 exposes session-authenticated JSON endpoints only — no Sanctum API routes
 * (decision P4) and no Inertia page until the frontend spec ships.
 */
final class VideoEditsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VideoEditRepositoryPort::class, EloquentVideoEditRepository::class);
        $this->app->bind(VideoEditProcessingDispatcherPort::class, QueuedVideoEditProcessingDispatcher::class);
        $this->app->bind(VideoEditWorkspacePort::class, LocalVideoEditWorkspace::class);

        $this->app->bind(CutPlanner::class, static fn (): CutPlanner => new CutPlanner(
            silencePaddingMs: (int) config('video-edit.silence.padding_ms'),
            minKeptFragmentMs: (int) config('video-edit.cuts.min_kept_fragment_ms'),
            minOutputMs: (int) config('video-edit.cuts.min_output_ms'),
        ));

        $this->registerDecisionProducers();
    }

    public function boot(): void
    {
        $this->registerWebRoutes();
        $this->registerExceptionRendering();

        if ($this->app->runningInConsole()) {
            $this->commands([
                PurgeExpiredVideoEditSourcesCommand::class,
                SweepStaleVideoEditsCommand::class,
            ]);
        }
    }

    /**
     * The V1 producers. V2/V3 producers are added to the same tag (EX-2).
     */
    private function registerDecisionProducers(): void
    {
        $this->app->tag([
            SilenceDecisionProducer::class,
            ManualRangeDecisionProducer::class,
        ], DecisionProducerRegistry::TAG);

        $this->app->bind(DecisionProducerRegistry::class, static fn (Application $app): DecisionProducerRegistry => new DecisionProducerRegistry(
            $app->tagged(DecisionProducerRegistry::TAG),
        ));
    }

    private function registerWebRoutes(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
    }

    /**
     * Maps the module's domain exceptions to user-safe JSON (plan §5 error
     * envelope). Kept here rather than in bootstrap/app.php so the module owns
     * its whole HTTP contract.
     */
    private function registerExceptionRendering(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! $handler instanceof Handler) {
            return;
        }

        $handler->renderable(static fn (VideoEditNotFoundException $exception): JsonResponse => response()->json([
            'message' => 'Video edit not found.',
            'code' => 'not_found',
        ], 404));

        $handler->renderable(static fn (VideoEditStateConflictException $exception): JsonResponse => response()->json([
            'message' => $exception->getMessage(),
            'code' => $exception->reasonCode,
        ], 409));

        $handler->renderable(static fn (SourceUploadInvalidException $exception): JsonResponse => response()->json([
            'message' => $exception->getMessage(),
            'code' => 'invalid_sources',
            'errors' => ['sources' => $exception->errorsBySourceUuid],
        ], 422));

        $handler->renderable(static fn (ManualRangesNotCorrectableException $exception): JsonResponse => response()->json([
            'message' => $exception->getMessage(),
            'code' => ManualRangesNotCorrectableException::CODE,
            'errors' => ['manual_ranges' => [$exception->getMessage()]],
        ], 422));

        $handler->renderable(static fn (InvalidCutRangesException $exception): JsonResponse => response()->json([
            'message' => $exception->getMessage(),
            'code' => InvalidCutRangesException::FAILURE_CODE,
            'errors' => $exception->details,
        ], 422));
    }
}
