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
use Modules\VideoEdits\Application\Pipeline\Producers\AiDecisionProducer;
use Modules\VideoEdits\Application\Pipeline\Producers\ManualRangeDecisionProducer;
use Modules\VideoEdits\Application\Pipeline\Producers\SilenceDecisionProducer;
use Modules\VideoEdits\Application\Pipeline\Producers\SpeechDecisionProducer;
use Modules\VideoEdits\Domain\Exceptions\AiConsentRequiredException;
use Modules\VideoEdits\Domain\Exceptions\InvalidCutRangesException;
use Modules\VideoEdits\Domain\Exceptions\ManualRangesNotCorrectableException;
use Modules\VideoEdits\Domain\Exceptions\ScriptUnreadableException;
use Modules\VideoEdits\Domain\Exceptions\SourceUploadInvalidException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditStateConflictException;
use Modules\VideoEdits\Domain\Ports\AiEditAnalysisPort;
use Modules\VideoEdits\Domain\Ports\AiReportStorePort;
use Modules\VideoEdits\Domain\Ports\ScriptProviderPort;
use Modules\VideoEdits\Domain\Ports\ScriptTextExtractorPort;
use Modules\VideoEdits\Domain\Ports\TranscriptionPort;
use Modules\VideoEdits\Domain\Ports\TranscriptStorePort;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\Ports\VideoEditProcessingDispatcherPort;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Domain\Ports\VideoEditWorkspacePort;
use Modules\VideoEdits\Domain\Services\CutPlanner;
use Modules\VideoEdits\Domain\Services\SpeechDisfluencyDetector;
use Modules\VideoEdits\Infrastructure\Ai\LaravelAiVideoEditAnalyzer;
use Modules\VideoEdits\Infrastructure\Ai\ScriptTextExtractor;
use Modules\VideoEdits\Infrastructure\Console\Commands\PurgeExpiredVideoEditSourcesCommand;
use Modules\VideoEdits\Infrastructure\Console\Commands\SweepStaleVideoEditsCommand;
use Modules\VideoEdits\Infrastructure\Media\FfmpegCommandBuilder;
use Modules\VideoEdits\Infrastructure\Media\FfmpegVideoEditor;
use Modules\VideoEdits\Infrastructure\Media\LocalVideoEditWorkspace;
use Modules\VideoEdits\Infrastructure\Persistence\Repositories\EloquentAiReportStore;
use Modules\VideoEdits\Infrastructure\Persistence\Repositories\EloquentScriptProvider;
use Modules\VideoEdits\Infrastructure\Persistence\Repositories\EloquentTranscriptStore;
use Modules\VideoEdits\Infrastructure\Persistence\Repositories\EloquentVideoEditRepository;
use Modules\VideoEdits\Infrastructure\Queue\QueuedVideoEditProcessingDispatcher;
use Modules\VideoEdits\Infrastructure\Transcription\OpenAiWhisperTranscriber;

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
        $this->app->bind(VideoEditorPort::class, FfmpegVideoEditor::class);
        $this->app->bind(TranscriptionPort::class, OpenAiWhisperTranscriber::class);
        $this->app->bind(TranscriptStorePort::class, EloquentTranscriptStore::class);
        $this->app->bind(AiEditAnalysisPort::class, LaravelAiVideoEditAnalyzer::class);
        $this->app->bind(AiReportStorePort::class, EloquentAiReportStore::class);
        $this->app->bind(ScriptProviderPort::class, EloquentScriptProvider::class);
        $this->app->bind(ScriptTextExtractorPort::class, ScriptTextExtractor::class);

        $this->registerFfmpegCommandBuilder();
        $this->registerSpeechDetector();

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
     * Binary paths and the process timeout come from `config/laravel-ffmpeg.php`
     * so FFmpeg is configured in one place; the encoder settings come from this
     * module's own config, which owns the output contract (D3).
     */
    private function registerFfmpegCommandBuilder(): void
    {
        $this->app->bind(FfmpegCommandBuilder::class, static fn (): FfmpegCommandBuilder => new FfmpegCommandBuilder(
            ffmpegBinary: (string) config('laravel-ffmpeg.ffmpeg.binaries', 'ffmpeg'),
            ffprobeBinary: (string) config('laravel-ffmpeg.ffprobe.binaries', 'ffprobe'),
            videoCodec: (string) config('video-edit.output.video_codec'),
            audioCodec: (string) config('video-edit.output.audio_codec'),
            pixelFormat: (string) config('video-edit.output.pixel_format'),
            crf: (int) config('video-edit.output.crf'),
            preset: (string) config('video-edit.output.preset'),
            intermediateCrf: (int) config('video-edit.output.intermediate_crf'),
            intermediatePreset: (string) config('video-edit.output.intermediate_preset'),
            audioBitrateKbps: (int) config('video-edit.output.audio_bitrate_kbps'),
            threads: is_numeric($threads = config('laravel-ffmpeg.ffmpeg.threads', false)) ? (int) $threads : false,
        ));
    }

    /**
     * Dictionaries come from config so a new filler word is a config change,
     * not a deploy of new domain code (R3).
     */
    private function registerSpeechDetector(): void
    {
        $this->app->bind(SpeechDisfluencyDetector::class, static fn (): SpeechDisfluencyDetector => new SpeechDisfluencyDetector(
            fillerSounds: (array) config('video-edit.speech.dictionaries.filler_sounds', []),
            fillerWords: (array) config('video-edit.speech.dictionaries.filler_words', []),
            fillerPhrases: (array) config('video-edit.speech.dictionaries.filler_phrases', []),
            repetitionAllowList: (array) config('video-edit.speech.dictionaries.repetition_allow_list', []),
            maxStutterFragmentMs: (int) config('video-edit.speech.max_stutter_fragment_ms'),
            minConfidence: (float) config('video-edit.speech.min_confidence'),
        ));
    }

    /**
     * V1 producers plus the V2 speech detector. Adding one to this tag is the
     * whole integration (EX-2) — validation, planning, rendering, persistence
     * and deletion were not touched to make V2 work.
     */
    private function registerDecisionProducers(): void
    {
        $this->app->tag([
            SilenceDecisionProducer::class,
            ManualRangeDecisionProducer::class,
            SpeechDecisionProducer::class,
            AiDecisionProducer::class,
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

        $handler->renderable(static fn (AiConsentRequiredException $exception): JsonResponse => response()->json([
            'message' => $exception->getMessage(),
            'code' => AiConsentRequiredException::FAILURE_CODE,
            'errors' => ['ai_edit.consented' => [$exception->getMessage()]],
        ], 422));

        $handler->renderable(static fn (ScriptUnreadableException $exception): JsonResponse => response()->json([
            'message' => $exception->getMessage(),
            'code' => ScriptUnreadableException::FAILURE_CODE,
            'errors' => ['ai_edit.script' => [$exception->getMessage()]],
        ], 422));

        $handler->renderable(static fn (InvalidCutRangesException $exception): JsonResponse => response()->json([
            'message' => $exception->getMessage(),
            'code' => InvalidCutRangesException::FAILURE_CODE,
            'errors' => $exception->details,
        ], 422));
    }
}
