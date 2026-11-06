<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Modules\VideoEdits\Application\Pipeline\DecisionProducerRegistry;
use Modules\VideoEdits\Application\Pipeline\Producers\AiDecisionProducer;
use Modules\VideoEdits\Application\Pipeline\Producers\FailedTakeReviewProducer;
use Modules\VideoEdits\Application\Pipeline\Producers\ManualRangeDecisionProducer;
use Modules\VideoEdits\Application\Pipeline\Producers\SilenceDecisionProducer;
use Modules\VideoEdits\Application\Pipeline\Producers\SpeechDecisionProducer;
use Modules\VideoEdits\Application\Transcription\TranscriptionIdentity;
use Modules\VideoEdits\Domain\Exceptions\AiConsentRequiredException;
use Modules\VideoEdits\Domain\Exceptions\InvalidCutRangesException;
use Modules\VideoEdits\Domain\Exceptions\InvalidCutReviewException;
use Modules\VideoEdits\Domain\Exceptions\ManualRangesNotCorrectableException;
use Modules\VideoEdits\Domain\Exceptions\ScriptUnreadableException;
use Modules\VideoEdits\Domain\Exceptions\SourceUploadInvalidException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditStateConflictException;
use Modules\VideoEdits\Domain\Ports\AiCutReviewStorePort;
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
use Modules\VideoEdits\Domain\Services\ClippedWordDetector;
use Modules\VideoEdits\Domain\Services\CutEdgeTrimmer;
use Modules\VideoEdits\Domain\Services\CutPlanner;
use Modules\VideoEdits\Domain\Services\CutSeamTrimmer;
use Modules\VideoEdits\Domain\Services\FailedTakeDetector;
use Modules\VideoEdits\Domain\Services\RestartDetector;
use Modules\VideoEdits\Domain\Services\SpeechDisfluencyDetector;
use Modules\VideoEdits\Domain\Services\SpeechPaceAnalyzer;
use Modules\VideoEdits\Domain\ValueObjects\VoiceChain;
use Modules\VideoEdits\Domain\ValueObjects\WaitCompressionPolicy;
use Modules\VideoEdits\Infrastructure\Ai\LaravelAiVideoEditAnalyzer;
use Modules\VideoEdits\Infrastructure\Ai\ScriptTextExtractor;
use Modules\VideoEdits\Infrastructure\Console\Commands\PurgeExpiredVideoEditSourcesCommand;
use Modules\VideoEdits\Infrastructure\Console\Commands\SweepStaleVideoEditsCommand;
use Modules\VideoEdits\Infrastructure\Media\FfmpegCommandBuilder;
use Modules\VideoEdits\Infrastructure\Media\FfmpegVideoEditor;
use Modules\VideoEdits\Infrastructure\Media\FilterGraphBuilder;
use Modules\VideoEdits\Infrastructure\Media\LocalVideoEditWorkspace;
use Modules\VideoEdits\Infrastructure\Persistence\Repositories\EloquentAiCutReviewStore;
use Modules\VideoEdits\Infrastructure\Persistence\Repositories\EloquentAiReportStore;
use Modules\VideoEdits\Infrastructure\Persistence\Repositories\EloquentScriptProvider;
use Modules\VideoEdits\Infrastructure\Persistence\Repositories\EloquentTranscriptStore;
use Modules\VideoEdits\Infrastructure\Persistence\Repositories\EloquentVideoEditRepository;
use Modules\VideoEdits\Infrastructure\Queue\QueuedVideoEditProcessingDispatcher;
use Modules\VideoEdits\Infrastructure\Transcription\GeminiTranscriber;
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
        // The transcription provider is a config switch rather than a code change:
        // whisper-1 is deprecated for 2027-02-26 and is the last OpenAI model that
        // returns word timings at all, so the port resolves to whichever adapter the
        // installation has chosen. See `video-edit.provider`.
        $this->app->bind(TranscriptionPort::class, static fn (): TranscriptionPort => self::transcriber());
        // The same switch, read as the two strings the transcript store keys
        // reuse on. Singleton rather than bind, because every producer and the
        // pipeline must agree on one identity per run — a transcript stored under
        // one provider and looked up under another is exactly the reuse bug this
        // exists to close.
        $this->app->singleton(TranscriptionIdentity::class);
        $this->app->bind(TranscriptStorePort::class, EloquentTranscriptStore::class);
        $this->app->bind(AiEditAnalysisPort::class, LaravelAiVideoEditAnalyzer::class);
        $this->app->bind(AiReportStorePort::class, EloquentAiReportStore::class);
        $this->app->bind(AiCutReviewStorePort::class, EloquentAiCutReviewStore::class);
        $this->app->bind(ScriptProviderPort::class, EloquentScriptProvider::class);
        $this->app->bind(ScriptTextExtractorPort::class, ScriptTextExtractor::class);

        $this->registerFfmpegCommandBuilder();
        $this->registerFilterGraphBuilder();
        $this->registerSpeechDetector();

        $this->app->bind(CutPlanner::class, static fn (): CutPlanner => new CutPlanner(
            silencePaddingAfterSpeechMs: (int) config('video-edit.silence.padding_after_speech_ms'),
            silencePaddingBeforeSpeechMs: (int) config('video-edit.silence.padding_before_speech_ms'),
            wordGuardAfterMs: (int) config('video-edit.silence.word_guard_after_ms'),
            wordGuardBeforeMs: (int) config('video-edit.silence.word_guard_before_ms'),
            minKeptFragmentMs: (int) config('video-edit.cuts.min_kept_fragment_ms'),
            minOutputMs: (int) config('video-edit.cuts.min_output_ms'),
            waitCompression: new WaitCompressionPolicy(
                enabled: (bool) config('video-edit.screen_activity.enabled'),
                minActivityRatio: (float) config('video-edit.screen_activity.min_activity_ratio'),
                keepHeadMs: (int) config('video-edit.screen_activity.keep_head_ms'),
                keepTailMs: (int) config('video-edit.screen_activity.keep_tail_ms'),
                speed: (float) config('video-edit.screen_activity.speed'),
                maxOutputMs: (int) config('video-edit.screen_activity.max_compressed_output_ms'),
            ),
            breathMinGapMs: (bool) config('video-edit.breaths.enabled') ? (int) config('video-edit.breaths.min_gap_ms') : 0,
            midSentencePauseMs: (int) config('video-edit.silence.mid_sentence_pause_ms'),
            edgeTrimmer: self::edgeTrimmer(),
            seamTrimmer: self::seamTrimmer(),
            onsetGuardMs: max(0, (int) config('video-edit.cuts.onset_guard_ms', 40)),
        ));

        $this->registerDecisionProducers();
    }

    public function boot(): void
    {
        $this->registerWebRoutes();
        $this->registerExceptionRendering();
        $this->registerApiDocumentation();

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
     * The seam trimmers are optional: with no absorb budget configured the
     * planner skips edge widening entirely, which is the behaviour every cut had
     * before this rule existed.
     */
    private static function edgeTrimmer(): ?CutEdgeTrimmer
    {
        $maxAbsorbMs = (int) config('video-edit.cuts.edge_absorb_ms', 0);

        if ($maxAbsorbMs <= 0) {
            return null;
        }

        return new CutEdgeTrimmer(
            maxAbsorbMs: $maxAbsorbMs,
            danglingWords: (array) config('video-edit.cuts.dangling_edge_words', []),
        );
    }

    /**
     * Resolved through the container rather than bound to a class, because the
     * switch lives in config and an unknown value must fail loudly: silently
     * falling back to Whisper would hide a typo until February 2027, when the
     * fallback stops existing.
     */
    private static function transcriber(): TranscriptionPort
    {
        $provider = (string) config('video-edit.provider', 'openai');

        return match ($provider) {
            'openai' => app(OpenAiWhisperTranscriber::class),
            'gemini' => app(GeminiTranscriber::class),
            default => throw new InvalidArgumentException("Unknown video-edit transcription provider [{$provider}]. Use openai or gemini."),
        };
    }

    /**
     * The filtergraph is the one place the final audio chain is assembled, so the
     * room-tone and loudness stages live there. Disabled, every value reaches the
     * constructor as null and the graph comes out exactly as it did before.
     *
     * Loudness is independent of the denoiser on purpose — a caller who wants
     * -16 LUFS on a clean desk still gets it with room tone dark — but when both
     * are on, {@see FilterGraphBuilder} orders denoise before normalise, which is
     * the only order that helps rather than hurts.
     */
    private function registerFilterGraphBuilder(): void
    {
        $this->app->bind(FilterGraphBuilder::class, static function (): FilterGraphBuilder {
            $enabled = (bool) config('video-edit.room_tone.enabled', false);
            $loudness = (bool) config('video-edit.loudness.enabled', false);
            $declick = (array) config('video-edit.cuts.declick', []);

            return new FilterGraphBuilder(
                denoiseReductionDb: $enabled ? (float) config('video-edit.room_tone.reduction_db') : null,
                denoiseFloorDb: $enabled ? (float) config('video-edit.room_tone.floor_db') : null,
                denoiseHighpassHz: $enabled ? (int) config('video-edit.room_tone.highpass_hz') : null,
                joinFadeMs: max(0, (int) config('video-edit.cuts.join_fade_ms', FilterGraphBuilder::DEFAULT_JOIN_FADE_MS)),
                joinAirMs: max(0, (int) config('video-edit.cuts.join_air_ms', FilterGraphBuilder::DEFAULT_JOIN_AIR_MS)),
                targetLufs: $loudness ? (float) config('video-edit.loudness.target_lufs') : null,
                truePeakDbTp: $loudness ? (float) config('video-edit.loudness.true_peak_dbtp') : null,
                loudnessRange: $loudness ? (float) config('video-edit.loudness.loudness_range') : null,
                voiceChain: self::voiceChain(),
                declickEnabled: (bool) ($declick['enabled'] ?? false),
                declickWindowMs: max(1, (int) ($declick['window_ms'] ?? FilterGraphBuilder::DEFAULT_DECLICK_WINDOW_MS)),
                declickOverlapPercent: max(50, min(95, (int) ($declick['overlap_percent'] ?? FilterGraphBuilder::DEFAULT_DECLICK_OVERLAP_PERCENT))),
                declickThreshold: max(1, min(100, (int) ($declick['threshold'] ?? FilterGraphBuilder::DEFAULT_DECLICK_THRESHOLD))),
                declickMethod: (string) ($declick['method'] ?? FilterGraphBuilder::DEFAULT_DECLICK_METHOD),
            );
        });
    }

    /**
     * Optional for the same reason as {@see self::edgeTrimmer}: a zero budget
     * leaves the seams exactly as they were, which is the behaviour every cut had
     * before this rule existed.
     *
     * Unlike the edge trimmer, a breath of zero is NOT a safe "off" — it would
     * mean welding the two flanks of silence together, which is the hole this
     * exists to remove. So only a non-positive ABSORB budget disables it; the
     * breath is floored at a value that still reads as a pause.
     */
    private static function seamTrimmer(): ?CutSeamTrimmer
    {
        $absorbMs = (int) config('video-edit.cuts.seam_absorb_ms', 0);

        if ($absorbMs <= 0) {
            return null;
        }

        return new CutSeamTrimmer(
            absorbMs: $absorbMs,
            breathMs: max(1, (int) config('video-edit.cuts.seam_breath_ms', 250)),
        );
    }

    /**
     * The OBS-equivalent chain, which is entirely off for a recording that came
     * through OBS — see the `voice_chain` block for why repeating those stages
     * would make the audio worse, not better.
     *
     * `arnndn` is the one stage that needs the filesystem, because FFmpeg fails
     * the whole render on an unreadable model. That check lives here rather than
     * in {@see VoiceChain}, which stays pure: a missing model is a configuration
     * mistake, so the stage is skipped and the render continues with the rest of
     * the chain instead of failing a render over one absent file.
     */
    private static function voiceChain(): ?VoiceChain
    {
        $rnnoise = (bool) config('video-edit.voice_chain.rnnoise.enabled', false);
        $compressor = (bool) config('video-edit.voice_chain.compressor.enabled', false);
        $expander = (bool) config('video-edit.voice_chain.expander.enabled', false);
        $limiter = (bool) config('video-edit.voice_chain.limiter.enabled', false);

        if (! $rnnoise && ! $compressor && ! $expander && ! $limiter) {
            return null;
        }

        $model = (string) config('video-edit.voice_chain.rnnoise.model', '');
        $modelPath = $rnnoise && $model !== '' && is_file($model) ? $model : null;

        $chain = new VoiceChain(
            compressor: $compressor ? [
                'threshold_db' => (float) config('video-edit.voice_chain.compressor.threshold_db'),
                'ratio' => (float) config('video-edit.voice_chain.compressor.ratio'),
                'attack_ms' => (float) config('video-edit.voice_chain.compressor.attack_ms'),
                'release_ms' => (float) config('video-edit.voice_chain.compressor.release_ms'),
                'makeup_db' => (float) config('video-edit.voice_chain.compressor.makeup_db'),
                'knee' => (float) config('video-edit.voice_chain.compressor.knee'),
            ] : null,
            expander: $expander ? [
                'threshold_db' => (float) config('video-edit.voice_chain.expander.threshold_db'),
                'ratio' => (float) config('video-edit.voice_chain.expander.ratio'),
                'attack_ms' => (float) config('video-edit.voice_chain.expander.attack_ms'),
                'release_ms' => (float) config('video-edit.voice_chain.expander.release_ms'),
                'range_db' => (float) config('video-edit.voice_chain.expander.range_db'),
            ] : null,
            limiter: $limiter ? [
                'ceiling_db' => (float) config('video-edit.voice_chain.limiter.ceiling_db'),
                'attack_ms' => (float) config('video-edit.voice_chain.limiter.attack_ms'),
                'release_ms' => (float) config('video-edit.voice_chain.limiter.release_ms'),
            ] : null,
            rnnoiseModel: $modelPath,
            rnnoiseMix: $modelPath === null ? null : (float) config('video-edit.voice_chain.rnnoise.mix', 1.0),
        );

        return $chain->isEmpty() ? null : $chain;
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
            pausePhrases: (array) config('video-edit.speech.dictionaries.pause_phrases', []),
            elongationPatterns: (array) config('video-edit.speech.dictionaries.elongation_patterns', []),
            maxStutterFragmentMs: (int) config('video-edit.speech.max_stutter_fragment_ms'),
            minConfidence: (float) config('video-edit.speech.min_confidence'),
        ));

        $this->app->bind(FailedTakeDetector::class, static fn (): FailedTakeDetector => new FailedTakeDetector(
            boundaryGapMs: (int) config('video-edit.failed_take.boundary_gap_ms', 400),
            maxLookbackMs: (int) config('video-edit.failed_take.max_lookback_ms', 8_000),
            minTakeMs: (int) config('video-edit.failed_take.min_take_ms', 400),
        ));

        $this->app->bind(RestartDetector::class, static fn (): RestartDetector => new RestartDetector(
            minPhraseWords: (int) config('video-edit.restart.min_phrase_words', 5),
            maxPhraseWords: (int) config('video-edit.restart.max_phrase_words', 8),
            minGapMs: (int) config('video-edit.restart.min_gap_ms', 700),
            maxRepeatDistanceMs: (int) config('video-edit.restart.max_repeat_distance_ms', 60_000),
            clusterMs: (int) config('video-edit.restart.cluster_ms', 2_000),
            maxInterludeWords: (int) config('video-edit.restart.max_interlude_words', 20),
        ));

        $this->app->bind(SpeechPaceAnalyzer::class, static fn (): SpeechPaceAnalyzer => new SpeechPaceAnalyzer(
            miniPauseMs: (int) config('video-edit.pace.mini_pause_ms'),
            minMiniPauses: (int) config('video-edit.pace.min_mini_pauses'),
            slowWordsPerMinute: (int) config('video-edit.pace.slow_words_per_minute'),
            minSpeakingMs: (int) config('video-edit.pace.min_speaking_ms'),
            defaultLanguage: (string) config('video-edit.speech.default_language'),
            rushRatio: (float) config('video-edit.pace.rush_ratio'),
            rushFloorWordsPerMinute: (int) config('video-edit.pace.rush_floor_words_per_minute'),
            rushMinTotalMs: (int) config('video-edit.pace.rush_min_total_ms'),
            rushMinWords: (int) config('video-edit.pace.rush_min_words'),
        ));

        $this->app->bind(ClippedWordDetector::class, static fn (): ClippedWordDetector => new ClippedWordDetector(
            minClippedWords: (int) config('video-edit.clarity.min_clipped_words', 3),
            maxReportedWords: (int) config('video-edit.clarity.max_reported_words', 20),
        ));
    }

    /**
     * V1 producers plus the V2 speech detector. Adding one to this tag is the
     * whole integration (EX-2) — validation, planning, rendering, persistence
     * and deletion were not touched to make V2 work.
     *
     * The order is load-bearing for one pair: `FailedTakeReviewProducer` reads
     * the transcript `SpeechDecisionProducer` stores in the same pass, so it has
     * to be tagged after it. Laravel preserves tag order, but swapping the two
     * would silently disable the failed-take review rather than fail loudly.
     */
    private function registerDecisionProducers(): void
    {
        $this->app->tag([
            SilenceDecisionProducer::class,
            ManualRangeDecisionProducer::class,
            SpeechDecisionProducer::class,
            FailedTakeReviewProducer::class,
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
     * The default Scramble document only covers `api/*`, and this module has no
     * Sanctum routes (P4), so its session JSON surface gets its own OpenAPI
     * document: `php artisan scramble:export --api=video-edits`. Export-only —
     * no extra public docs route is exposed.
     */
    private function registerApiDocumentation(): void
    {
        Scramble::registerApi('video-edits', [
            'api_path' => 'data/admin/video-edits',
            'export_path' => 'api-video-edits.json',
            'info' => [
                'version' => '1.0.0',
                'description' => 'Video Edits JSON endpoints (session auth, spec 001-video-edit).',
            ],
        ])->expose(false);
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

        $handler->renderable(static fn (InvalidCutReviewException $exception): JsonResponse => response()->json([
            'message' => $exception->getMessage(),
            'code' => InvalidCutReviewException::CODE,
            'errors' => ['approved_cut_ids' => [$exception->getMessage()]],
        ], 422));

        $handler->renderable(static fn (InvalidCutRangesException $exception): JsonResponse => response()->json([
            'message' => $exception->getMessage(),
            'code' => InvalidCutRangesException::FAILURE_CODE,
            'errors' => $exception->details,
        ], 422));
    }
}
