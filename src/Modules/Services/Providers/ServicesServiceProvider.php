<?php

declare(strict_types=1);

namespace Modules\Services\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Services\Application\Queries\ListPublicServicesHandler;
use Modules\Services\Infrastructure\Cache\ServicePublicFeedCache;

/**
 * Composition root for the Services module.
 *
 * No repository binding: {@see ServiceEloquentModel} has exactly one
 * implementation, no decorator, and every handler is covered by Feature
 * tests hitting the real database — the Repository Optionality Rule's SKIP
 * criteria all hold (`ARCHITECTURE-PHP/SKILL-SIMPLE-CRUD.md`).
 */
final class ServicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ListPublicServicesHandler::class,
            static fn (Application $app): ListPublicServicesHandler => new ListPublicServicesHandler(
                $app->make(Cache::class),
                ServicePublicFeedCache::PUBLIC_CACHE_KEY,
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
     * The public feed is a cache hit for almost every caller and has no
     * user to key it by, so it is generous and keyed by IP — same shape as
     * `CompanyServiceProvider::configureRateLimiters()`.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for(
            'public-services',
            static fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip()),
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
