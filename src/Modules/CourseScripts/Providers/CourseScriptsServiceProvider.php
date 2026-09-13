<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Exceptions\EncryptedPdfException;
use Modules\CourseScripts\Domain\Exceptions\MissingCourseTitleException;
use Modules\CourseScripts\Domain\Exceptions\NoTextLayerException;
use Modules\CourseScripts\Domain\Exceptions\NothingGeneratedException;
use Modules\CourseScripts\Domain\Exceptions\RunInProgressException;
use Modules\CourseScripts\Domain\Exceptions\RunRequestRejectedException;
use Modules\CourseScripts\Domain\Exceptions\SourceDocumentLimitException;
use Modules\CourseScripts\Domain\Exceptions\UnrecognisableIndexException;
use Modules\CourseScripts\Domain\Ports\BibleProposerPort;
use Modules\CourseScripts\Domain\Ports\CourseBundlePort;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\DeliverableRendererPort;
use Modules\CourseScripts\Domain\Ports\DocumentTextExtractorPort;
use Modules\CourseScripts\Domain\Ports\GenerationDispatcherPort;
use Modules\CourseScripts\Domain\Ports\GenerationRunRepositoryPort;
use Modules\CourseScripts\Domain\Ports\IndexDocumentParserPort;
use Modules\CourseScripts\Domain\Ports\ResearchFindingRepositoryPort;
use Modules\CourseScripts\Domain\Ports\ResearchPort;
use Modules\CourseScripts\Domain\Ports\ScriptReviewerPort;
use Modules\CourseScripts\Domain\Ports\ScriptVersionRepositoryPort;
use Modules\CourseScripts\Domain\Ports\ScriptWriterPort;
use Modules\CourseScripts\Domain\Services\CallEstimator;
use Modules\CourseScripts\Domain\Services\ContactDataValidator;
use Modules\CourseScripts\Domain\Services\ContinuityContextBuilder;
use Modules\CourseScripts\Domain\Services\CourseIndexValidator;
use Modules\CourseScripts\Domain\Services\NotesExcerptSelector;
use Modules\CourseScripts\Domain\Services\ScriptOutlineValidator;
use Modules\CourseScripts\Domain\Services\TableArithmeticValidator;
use Modules\CourseScripts\Infrastructure\Ai\LaravelAiBibleProposerAdapter;
use Modules\CourseScripts\Infrastructure\Ai\LaravelAiScriptReviewerAdapter;
use Modules\CourseScripts\Infrastructure\Ai\LaravelAiScriptWriterAdapter;
use Modules\CourseScripts\Infrastructure\Parsing\DocumentTextExtractor;
use Modules\CourseScripts\Infrastructure\Parsing\IndexDocumentParser;
use Modules\CourseScripts\Infrastructure\Parsing\MarkdownIndexParser;
use Modules\CourseScripts\Infrastructure\Parsing\PdfIndexParser;
use Modules\CourseScripts\Infrastructure\Persistence\Repositories\EloquentCourseRepository;
use Modules\CourseScripts\Infrastructure\Persistence\Repositories\EloquentGenerationRunRepository;
use Modules\CourseScripts\Infrastructure\Persistence\Repositories\EloquentResearchFindingRepository;
use Modules\CourseScripts\Infrastructure\Persistence\Repositories\EloquentScriptVersionRepository;
use Modules\CourseScripts\Infrastructure\Queue\QueuedGenerationDispatcher;
use Modules\CourseScripts\Infrastructure\Rendering\DocumentDeliverableRenderer;
use Modules\CourseScripts\Infrastructure\Rendering\ZipCourseBundleBuilder;
use Modules\CourseScripts\Infrastructure\Research\LaravelResearchAdapter;
use Smalot\PdfParser\Parser as SmalotParser;
use Throwable;

/**
 * Composition root of the Course Scripts module (spec 002-course-scripts).
 *
 * Session-authenticated Inertia pages and JSON actions under `/course-scripts`
 * (plan §5). The module owns its whole HTTP contract: routes, per-user rate
 * limiters and the rendering of its domain exceptions.
 */
final class CourseScriptsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerIndexParsing();

        $this->app->bind(CourseRepositoryPort::class, EloquentCourseRepository::class);
        $this->app->bind(GenerationRunRepositoryPort::class, EloquentGenerationRunRepository::class);
        $this->app->bind(ResearchFindingRepositoryPort::class, EloquentResearchFindingRepository::class);
        $this->app->bind(ResearchPort::class, LaravelResearchAdapter::class);
        $this->app->bind(BibleProposerPort::class, LaravelAiBibleProposerAdapter::class);
        $this->app->bind(ScriptWriterPort::class, LaravelAiScriptWriterAdapter::class);
        $this->app->bind(ScriptReviewerPort::class, LaravelAiScriptReviewerAdapter::class);
        $this->app->bind(ScriptVersionRepositoryPort::class, EloquentScriptVersionRepository::class);
        $this->app->bind(GenerationDispatcherPort::class, QueuedGenerationDispatcher::class);
        $this->app->bind(DeliverableRendererPort::class, DocumentDeliverableRenderer::class);
        $this->app->bind(CourseBundlePort::class, ZipCourseBundleBuilder::class);

        $this->registerGenerationServices();
    }

    public function boot(): void
    {
        $this->registerRateLimiters();
        $this->registerWebRoutes();
        $this->registerExceptionRendering();
    }

    /**
     * Markdown first: it is the cheaper branch and the one the PDF adapter
     * delegates into. Limits come from config so tuning is an ops change.
     */
    private function registerIndexParsing(): void
    {
        $this->app->singleton(MarkdownIndexParser::class);

        $this->app->bind(PdfIndexParser::class, static fn (): PdfIndexParser => new PdfIndexParser(
            grammar: app(MarkdownIndexParser::class),
            parser: new SmalotParser,
            minimumTextLength: (int) config('course-scripts.uploads.pdf_min_text_length', 200),
        ));

        $this->app->bind(IndexDocumentParserPort::class, static fn (): IndexDocumentParser => new IndexDocumentParser([
            app(MarkdownIndexParser::class),
            app(PdfIndexParser::class),
        ]));

        $this->app->bind(DocumentTextExtractorPort::class, DocumentTextExtractor::class);

        $this->app->bind(CourseIndexValidator::class, static fn (): CourseIndexValidator => new CourseIndexValidator(
            minPoints: (int) config('course-scripts.structure.min_videos', 1),
            maxPoints: (int) config('course-scripts.structure.max_videos', 200),
            maxGroups: (int) config('course-scripts.structure.max_blocks', 20),
        ));
    }

    /**
     * Pure domain services configured from `config/course-scripts.php`, so a
     * tolerance or a budget is an ops change rather than a deploy.
     */
    private function registerGenerationServices(): void
    {
        $this->app->bind(ScriptOutlineValidator::class, static fn (): ScriptOutlineValidator => new ScriptOutlineValidator(
            tolerancePct: (int) config('course-scripts.script.time_budget_tolerance_pct', 10),
            minSections: (int) config('course-scripts.script.min_sections', 3),
            maxSections: (int) config('course-scripts.script.max_sections', 12),
            maxArtifacts: (int) config('course-scripts.practice.max_artifacts_per_pack', 4),
        ));

        $this->app->bind(TableArithmeticValidator::class, static fn (): TableArithmeticValidator => new TableArithmeticValidator(
            tolerance: (float) config('course-scripts.practice.table_total_tolerance', 0.01),
        ));

        $this->app->bind(ContactDataValidator::class, static fn (): ContactDataValidator => new ContactDataValidator(
            domainDenylist: (array) config('course-scripts.practice.contact_domain_denylist', []),
        ));

        $this->app->bind(ContinuityContextBuilder::class, static fn (): ContinuityContextBuilder => new ContinuityContextBuilder(
            window: (int) config('course-scripts.continuity.predecessor_window', 3),
            maxSummaryChars: (int) config('course-scripts.continuity.max_summary_length', 400),
        ));

        $this->app->bind(NotesExcerptSelector::class, static fn (): NotesExcerptSelector => new NotesExcerptSelector(
            budgetChars: (int) config('course-scripts.notes.excerpt_budget_chars', 6000),
            maxExcerpts: (int) config('course-scripts.notes.max_excerpts', 8),
        ));

        $this->app->bind(CallEstimator::class, static fn (): CallEstimator => new CallEstimator(
            minutesPerSection: (float) config('course-scripts.script.minutes_per_section', 1.5),
            minSections: (int) config('course-scripts.script.min_sections', 3),
            maxSections: (int) config('course-scripts.script.max_sections', 12),
            practiceRatio: (float) config('course-scripts.script.practice_ratio', 0.6),
            avgArtifactsPerPack: (float) config('course-scripts.practice.avg_artifacts_per_pack', 2),
            expectedRewriteRounds: (float) config('course-scripts.runs.expected_rewrite_rounds', 0.5),
            pointQueriesMax: (int) config('course-scripts.research.point_queries_max', 2),
            subjectQueries: (int) config('course-scripts.research.subject_queries', 4),
            maxFirecrawlPerVideo: (int) config('course-scripts.research.max_firecrawl_per_video', 2),
            firecrawlEnabled: (bool) config('course-scripts.research.firecrawl_enabled', true),
        ));
    }

    /**
     * Per user, per minute (FR-57, OWASP §14). The call ceilings guard the
     * wallet; these guard the endpoints.
     */
    private function registerRateLimiters(): void
    {
        foreach (['upload', 'generate', 'status', 'download', 'export'] as $name) {
            RateLimiter::for('course-scripts-'.$name, static fn (Request $request): Limit => Limit::perMinute(
                (int) config('course-scripts.rate_limits.'.$name, 30),
            )->by('course-scripts-'.$name.':'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        }
    }

    private function registerWebRoutes(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
    }

    /**
     * Domain exceptions become user-safe responses: JSON envelopes for XHR,
     * validation errors on the right form field for Inertia forms. Never a
     * stack trace, a path or provider text (OWASP §10).
     */
    private function registerExceptionRendering(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! $handler instanceof Handler) {
            return;
        }

        $handler->renderable(static fn (CourseNotFoundException $exception, Request $request): JsonResponse => response()->json([
            'message' => 'Course not found.',
            'code' => CourseNotFoundException::CODE,
        ], 404));

        $handler->renderable(static fn (RunInProgressException $exception): JsonResponse => response()->json([
            'message' => $exception->getMessage(),
            'code' => RunInProgressException::CODE,
        ], 409));

        $handler->renderable(static fn (NothingGeneratedException $exception): JsonResponse => response()->json([
            'message' => $exception->getMessage(),
            'code' => NothingGeneratedException::CODE,
        ], 409));

        $handler->renderable(static fn (RunRequestRejectedException $exception): JsonResponse => response()->json([
            'message' => $exception->getMessage(),
            'code' => $exception->reasonCode,
            ...$exception->details,
        ], 422));

        $unprocessable = [
            UnrecognisableIndexException::class => ['index', UnrecognisableIndexException::CODE],
            EncryptedPdfException::class => ['index', EncryptedPdfException::CODE],
            NoTextLayerException::class => ['index', NoTextLayerException::CODE],
            MissingCourseTitleException::class => ['title', MissingCourseTitleException::CODE],
            SourceDocumentLimitException::class => ['file', SourceDocumentLimitException::CODE],
        ];

        foreach ($unprocessable as $class => [$field, $code]) {
            $handler->renderable(static function (Throwable $exception, Request $request) use ($class, $field, $code): JsonResponse|RedirectResponse|null {
                if (! $exception instanceof $class) {
                    return null;
                }

                $message = $exception->getMessage();
                $details = $exception instanceof UnrecognisableIndexException ? $exception->reasons : [$message];

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $message,
                        'code' => $code,
                        'errors' => [$field => $details],
                    ], 422);
                }

                return redirect()->back()->withErrors([$field => implode(' ', $details)]);
            });
        }
    }
}
