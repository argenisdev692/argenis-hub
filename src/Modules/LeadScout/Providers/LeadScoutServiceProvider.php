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
use Modules\LeadScout\Domain\Exceptions\BudgetExceededException;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Exceptions\ContactNotFoundException;
use Modules\LeadScout\Domain\Exceptions\CvNotFoundException;
use Modules\LeadScout\Domain\Exceptions\CvNotImportableException;
use Modules\LeadScout\Domain\Exceptions\DecisionRuleLockedException;
use Modules\LeadScout\Domain\Exceptions\PersonOpposedException;
use Modules\LeadScout\Domain\Exceptions\ProfileNotFoundException;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Domain\Exceptions\TierNotContactableException;
use Modules\LeadScout\Domain\Ports\AiModelCatalogPort;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\CvSourcePort;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Domain\Ports\SearchPort;
use Modules\LeadScout\Infrastructure\Ai\ConfigAiModelCatalog;
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
use Modules\LeadScout\Infrastructure\JobSources\ArbeitnowApiSource;
use Modules\LeadScout\Infrastructure\JobSources\RssFeedSource;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentCompanyRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentJobPostingRepository;
use Modules\LeadScout\Infrastructure\Persistence\Repositories\EloquentOutreachRepository;
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

        $this->app->tag(
            [RssFeedSource::class, ArbeitnowApiSource::class],
            'lead-scout.job-sources',
        );

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
