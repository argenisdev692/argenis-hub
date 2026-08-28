<?php

declare(strict_types=1);

namespace Modules\Portfolios\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Portfolios\Application\Queries\ListPublicPortfoliosHandler;
use Modules\Portfolios\Infrastructure\Cache\PortfolioPublicFeedCache;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

/**
 * Composition root for the Portfolios module.
 *
 * No repository binding: {@see PortfolioEloquentModel} has exactly one
 * implementation, no decorator, and every handler is covered by Feature tests
 * hitting the real database — the Repository Optionality Rule's SKIP criteria
 * all hold (`ARCHITECTURE-PHP/SKILL-SIMPLE-CRUD.md`). The provider injects the
 * public-feed cache key into {@see ListPublicPortfoliosHandler} so the
 * Application layer never names an Infrastructure constant.
 */
final class PortfoliosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ListPublicPortfoliosHandler::class,
            static fn (Application $app): ListPublicPortfoliosHandler => new ListPublicPortfoliosHandler(
                $app->make(Cache::class),
                PortfolioPublicFeedCache::PUBLIC_CACHE_KEY,
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
     * The showcase feed is a cache hit for almost every caller and has no user
     * to key it by, so the list limiter is generous and keyed by IP — same
     * shape as `ServicesServiceProvider::configureRateLimiters()`. The export
     * limiter is an order of magnitude tighter: an anonymous PDF render is a
     * real amplification vector.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for(
            'public-portfolios',
            static fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip()),
        );

        RateLimiter::for(
            'public-portfolios-export',
            static fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()),
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
