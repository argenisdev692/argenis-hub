<?php

declare(strict_types=1);

namespace Modules\LeadScout\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Modules\LeadScout\Application\Commands\IngestSourceHandler;
use Modules\LeadScout\Domain\Exceptions\BudgetExceededException;
use Modules\LeadScout\Domain\Exceptions\ChannelNotFoundException;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Exceptions\ContactNotFoundException;
use Modules\LeadScout\Domain\Exceptions\CvNotFoundException;
use Modules\LeadScout\Domain\Exceptions\CvNotImportableException;
use Modules\LeadScout\Domain\Exceptions\DecisionRuleLockedException;
use Modules\LeadScout\Domain\Exceptions\InvalidInputException;
use Modules\LeadScout\Domain\Exceptions\PersonOpposedException;
use Modules\LeadScout\Domain\Exceptions\ProfileNotFoundException;
use Modules\LeadScout\Domain\Exceptions\SourceNotFoundException;
use Modules\LeadScout\Domain\Exceptions\SourceTermsNotReviewedException;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Domain\Exceptions\TierNotContactableException;
use Modules\LeadScout\Domain\Ports\AiModelCatalogPort;
use Modules\LeadScout\Domain\Ports\AiSettingRepositoryPort;
use Modules\LeadScout\Domain\Ports\BudgetLedgerPort;
use Modules\LeadScout\Domain\Ports\CircuitBreakerPort;
use Modules\LeadScout\Domain\Ports\CompanyPageFetcherPort;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\ContactChannelRepositoryPort;
use Modules\LeadScout\Domain\Ports\ContactRepositoryPort;
use Modules\LeadScout\Domain\Ports\CvSourcePort;
use Modules\LeadScout\Domain\Ports\DecisionRuleRepositoryPort;
use Modules\LeadScout\Domain\Ports\DraftWriterPort;
use Modules\LeadScout\Domain\Ports\FetchedPageRepositoryPort;
use Modules\LeadScout\Domain\Ports\FunnelMetricsReadPort;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Domain\Ports\LeadReadRepositoryPort;
use Modules\LeadScout\Domain\Ports\OpportunityRepositoryPort;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Domain\Ports\PipelineLoggerPort;
use Modules\LeadScout\Domain\Ports\PipelineQueuePort;
use Modules\LeadScout\Domain\Ports\PrivacyRequestRepositoryPort;
use Modules\LeadScout\Domain\Ports\ProfileRepositoryPort;
use Modules\LeadScout\Domain\Ports\ScoreResultRepositoryPort;
use Modules\LeadScout\Domain\Ports\SearchPort;
use Modules\LeadScout\Domain\Ports\SignalExtractorPort;
use Modules\LeadScout\Domain\Ports\SignalRepositoryPort;
use Modules\LeadScout\Domain\Ports\SourceRepositoryPort;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Domain\Ports\TabularFileReaderPort;
use Modules\LeadScout\Domain\Ports\TransactionPort;
use Modules\LeadScout\Infrastructure\Ai\ConfigAiModelCatalog;
use Modules\LeadScout\Infrastructure\Ai\LaravelAiDraftWriter;
use Modules\LeadScout\Infrastructure\Ai\LaravelAiSignalExtractor;
use Modules\LeadScout\Infrastructure\Budgets\BudgetLedger;
use Modules\LeadScout\Infrastructure\Console\Commands\LeadScoutBackupCommand;
use Modules\LeadScout\Infrastructure\Console\Commands\LeadScoutDiscoverCommand;
use Modules\LeadScout\Infrastructure\Console\Commands\LeadScoutExpireCommand;
use Modules\LeadScout\Infrastructure\Console\Commands\LeadScoutImportDgcCommand;
use Modules\LeadScout\Infrastructure\Console\Commands\LeadScoutImportLeadsCommand;
use Modules\LeadScout\Infrastructure\Console\Commands\LeadScoutIngestCommand;
use Modules\LeadScout\Infrastructure\Console\Commands\LeadScoutPrivacyCommand;
use Modules\LeadScout\Infrastructure\Console\Commands\LeadScoutPruneCommand;
use Modules\LeadScout\Infrastructure\Console\Commands\LeadScoutScoreCommand;
use Modules\LeadScout\Infrastructure\Cvs\EloquentCvSource;
use Modules\LeadScout\Infrastructure\Fetching\FetchLadder;
use Modules\LeadScout\Infrastructure\Imports\SimpleExcelTabularReader;
use Modules\LeadScout\Infrastructure\JobSources\ArbeitnowApiSource;
use Modules\LeadScout\Infrastructure\JobSources\RssFeedSource;
use Modules\LeadScout\Infrastructure\Logging\ApplicationLogger;
use Modules\LeadScout\Infrastructure\Persistence\DatabaseTransaction;
use Modules\LeadScout\Infrastructure\Persistence\ReadRepositories\EloquentFunnelMetricsReadRepository;
use Modules\LeadScout\Infrastructure\Persistence\ReadRepositories\EloquentLeadReadRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentAiSettingRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentCompanyRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentContactChannelRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentContactRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentDecisionRuleRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentFetchedPageRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentJobPostingRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentOpportunityRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentOutreachRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentPrivacyRequestRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentProfileRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentScoreResultRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentSignalRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentSourceRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentSuppressionRepository;
use Modules\LeadScout\Infrastructure\Queue\LaravelPipelineQueue;
use Modules\LeadScout\Infrastructure\Resilience\SharedCircuitBreaker;
use Modules\LeadScout\Infrastructure\Search\TavilySearchAdapter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Composition root of the LeadScout module (spec 003-lead-scout).
 *
 * Session-authenticated JSON surface under `/data/admin/lead-scout`
 * (plan §5). Port bindings land with the phase that introduces each port.
 */
final class LeadScoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CvSourcePort::class, EloquentCvSource::class);
        $this->app->bind(CompanyRepositoryPort::class, EloquentCompanyRepository::class);
        $this->app->bind(JobPostingRepositoryPort::class, EloquentJobPostingRepository::class);
        $this->app->bind(OutreachRepositoryPort::class, EloquentOutreachRepository::class);
        $this->app->bind(SearchPort::class, TavilySearchAdapter::class);
        $this->app->bind(AiModelCatalogPort::class, ConfigAiModelCatalog::class);
        $this->app->bind(TransactionPort::class, DatabaseTransaction::class);
        $this->app->bind(ProfileRepositoryPort::class, EloquentProfileRepository::class);
        $this->app->bind(SuppressionRepositoryPort::class, EloquentSuppressionRepository::class);
        $this->app->bind(TabularFileReaderPort::class, SimpleExcelTabularReader::class);
        $this->app->bind(ContactRepositoryPort::class, EloquentContactRepository::class);
        $this->app->bind(PrivacyRequestRepositoryPort::class, EloquentPrivacyRequestRepository::class);
        $this->app->bind(FetchedPageRepositoryPort::class, EloquentFetchedPageRepository::class);
        $this->app->bind(DecisionRuleRepositoryPort::class, EloquentDecisionRuleRepository::class);
        $this->app->bind(AiSettingRepositoryPort::class, EloquentAiSettingRepository::class);
        $this->app->bind(OpportunityRepositoryPort::class, EloquentOpportunityRepository::class);
        $this->app->bind(ContactChannelRepositoryPort::class, EloquentContactChannelRepository::class);
        $this->app->bind(PipelineLoggerPort::class, ApplicationLogger::class);
        $this->app->bind(PipelineQueuePort::class, LaravelPipelineQueue::class);
        $this->app->bind(BudgetLedgerPort::class, BudgetLedger::class);
        $this->app->bind(SignalExtractorPort::class, LaravelAiSignalExtractor::class);
        $this->app->bind(FunnelMetricsReadPort::class, EloquentFunnelMetricsReadRepository::class);
        $this->app->bind(LeadReadRepositoryPort::class, EloquentLeadReadRepository::class);
        $this->app->bind(DraftWriterPort::class, LaravelAiDraftWriter::class);

        $this->app->bind(SourceRepositoryPort::class, EloquentSourceRepository::class);
        $this->app->bind(CircuitBreakerPort::class, SharedCircuitBreaker::class);
        $this->app->bind(SignalRepositoryPort::class, EloquentSignalRepository::class);
        $this->app->bind(ScoreResultRepositoryPort::class, EloquentScoreResultRepository::class);
        $this->app->bind(CompanyPageFetcherPort::class, FetchLadder::class);

        $this->app->tag(
            [RssFeedSource::class, ArbeitnowApiSource::class],
            'lead-scout.job-sources',
        );
        $this->app->when(IngestSourceHandler::class)->needs('$adapters')->giveTagged('lead-scout.job-sources');

        $this->commands([
            LeadScoutIngestCommand::class,
            LeadScoutExpireCommand::class,
            LeadScoutImportLeadsCommand::class,
            LeadScoutScoreCommand::class,
            LeadScoutDiscoverCommand::class,
            LeadScoutPrivacyCommand::class,
            LeadScoutPruneCommand::class,
            LeadScoutImportDgcCommand::class,
            LeadScoutBackupCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->registerRateLimiters();
        $this->registerWebRoutes();
        $this->registerApiDocumentation();
        $this->registerExceptionMapping();
    }

    /**
     * Per user, per minute (OWASP §14, LLM10). The `lead-scout-llm` limiter
     * guards every draft/signal generation route (T075).
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for(
            'lead-scout-llm',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('lead-scout.rate_limits.llm', 10),
            )->by('lead-scout-llm:'.($request->user()?->getAuthIdentifier() ?? $request->ip())),
        );

        RateLimiter::for(
            'lead-scout-export',
            static fn (Request $request): Limit => Limit::perMinute(
                (int) config('lead-scout.rate_limits.export', 10),
            )->by('lead-scout-export:'.($request->user()?->getAuthIdentifier() ?? $request->ip())),
        );
    }

    private function registerWebRoutes(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
    }

    /**
     * Domain misses become HTTP semantics without leaking internals
     * (OWASP §10): unknown/foreign CVs and missing profiles → 404,
     * textless CVs → 422 with the reason.
     */
    private function registerExceptionMapping(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! $handler instanceof Handler) {
            return;
        }

        $handler->map(
            InvalidInputException::class,
            static fn (InvalidInputException $e): ValidationException => ValidationException::withMessages($e->errors),
        );

        $handler->map(
            CvNotFoundException::class,
            static fn (CvNotFoundException $e): NotFoundHttpException => new NotFoundHttpException($e->getMessage(), $e),
        );

        $handler->map(
            ProfileNotFoundException::class,
            static fn (ProfileNotFoundException $e): NotFoundHttpException => new NotFoundHttpException($e->getMessage(), $e),
        );

        $handler->map(
            CompanyNotFoundException::class,
            static fn (CompanyNotFoundException $e): NotFoundHttpException => new NotFoundHttpException($e->getMessage(), $e),
        );

        $handler->map(
            SourceNotFoundException::class,
            static fn (SourceNotFoundException $e): NotFoundHttpException => new NotFoundHttpException($e->getMessage(), $e),
        );

        // Activation without reviewed terms (spec FR-13).
        $handler->renderable(
            static fn (SourceTermsNotReviewedException $e, Request $request): ?JsonResponse => $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'code' => SourceTermsNotReviewedException::CODE], 422)
                : null,
        );

        $handler->map(
            ChannelNotFoundException::class,
            static fn (ChannelNotFoundException $e): NotFoundHttpException => new NotFoundHttpException($e->getMessage(), $e),
        );

        $handler->map(
            ContactNotFoundException::class,
            static fn (ContactNotFoundException $e): NotFoundHttpException => new NotFoundHttpException($e->getMessage(), $e),
        );

        // Opposed people answer 409 (spec US-11 CA-10).
        $handler->renderable(
            static fn (PersonOpposedException $e, Request $request): ?JsonResponse => $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'code' => PersonOpposedException::CODE], 409)
                : null,
        );

        // Locked decision rules are immutable (spec FR-19).
        $handler->renderable(
            static fn (DecisionRuleLockedException $e, Request $request): ?JsonResponse => $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'code' => DecisionRuleLockedException::CODE], 409)
                : null,
        );

        // Drafts are Tier A/B only (spec FR-40).
        $handler->renderable(
            static fn (TierNotContactableException $e, Request $request): ?JsonResponse => $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'code' => TierNotContactableException::CODE], 409)
                : null,
        );

        $handler->renderable(
            static fn (CvNotImportableException $e, Request $request): ?JsonResponse => $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'code' => CvNotImportableException::CODE], 422)
                : null,
        );

        // Suppressed companies are rejected with 409 everywhere (FR-17/FR-43).
        $handler->renderable(
            static fn (SuppressedException $e, Request $request): ?JsonResponse => $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'code' => SuppressedException::CODE], 409)
                : null,
        );

        // Exhausted budgets degrade to free sources with 402 (FR-14).
        $handler->renderable(
            static fn (BudgetExceededException $e, Request $request): ?JsonResponse => $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'code' => BudgetExceededException::CODE], 402)
                : null,
        );
    }

    /**
     * The default Scramble document only covers `api/*` and this module has
     * no Sanctum routes, so its session JSON surface gets its own OpenAPI
     * document: `php artisan scramble:export --api=lead-scout`. Export-only
     * (CourseScripts precedent) — no extra public docs route is exposed.
     */
    private function registerApiDocumentation(): void
    {
        Scramble::registerApi('lead-scout', [
            'api_path' => 'data/admin/lead-scout',
            'export_path' => 'api-lead-scout.json',
            'info' => [
                'version' => '1.0.0',
                'description' => 'LeadScout endpoints (session auth, spec 003-lead-scout).',
            ],
        ])->expose(false);
    }
}
