<?php

declare(strict_types=1);

namespace Modules\Company\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Company\Application\Queries\GetPublicCompanyHandler;
use Modules\Company\Domain\Ports\CompanyLogoStoragePort;
use Modules\Company\Domain\Ports\CompanyRepositoryPort;
use Modules\Company\Infrastructure\Persistence\Repositories\EloquentCompanyRepository;
use Modules\Company\Infrastructure\Storage\CompanyLogoStorage;
use Shared\Infrastructure\Company\CompanyProfile;

/**
 * Composition root for the Company module.
 *
 * The cache key for the public endpoint is injected from here rather than
 * hard-coded in the query handler. That keeps the Application layer from naming
 * an Infrastructure constant while still leaving exactly one definition of the
 * key: CompanyProfile owns it, CompanyProfile::forget() flushes it, and the
 * model saved/deleted hooks call that on every write. One key, one invalidation
 * point, no literal repeated across layers.
 */
final class CompanyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompanyRepositoryPort::class, EloquentCompanyRepository::class);
        $this->app->bind(CompanyLogoStoragePort::class, CompanyLogoStorage::class);

        $this->app->bind(
            GetPublicCompanyHandler::class,
            static fn (Application $app): GetPublicCompanyHandler => new GetPublicCompanyHandler(
                $app->make(CompanyRepositoryPort::class),
                $app->make(CompanyLogoStoragePort::class),
                $app->make(Cache::class),
                CompanyProfile::PUBLIC_CACHE_KEY,
            ),
        );
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->registerWebRoutes();
        $this->registerApiRoutes();
    }

    /**
     * Two limiters, sized to what each endpoint actually costs.
     *
     * The public read is a cache hit for almost every caller, so it is generous
     * and keyed by IP — there is no user to key it by. The logo upload decodes
     * and re-encodes an image on the request thread, which is the most expensive
     * thing this module does, so it is keyed per operator and kept low.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for(
            'public-company',
            static fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip()),
        );

        RateLimiter::for(
            'company-logos',
            static fn (Request $request): Limit => Limit::perMinute(10)
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())),
        );
    }

    private function registerWebRoutes(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
    }

    private function registerApiRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../Infrastructure/Routes/api.php');
    }
}
