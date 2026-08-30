<?php

declare(strict_types=1);

namespace Modules\Blog\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Blog\Domain\Ports\BlogCategoryPublicFeedCachePort;
use Modules\Blog\Domain\Ports\BlogCategoryRepositoryPort;
use Modules\Blog\Infrastructure\Cache\BlogCategoryPublicFeedCache;
use Modules\Blog\Infrastructure\Persistence\Repositories\EloquentBlogCategoryRepository;

/**
 * Composition root for the Blog module.
 */
final class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BlogCategoryRepositoryPort::class, EloquentBlogCategoryRepository::class);
        $this->app->bind(BlogCategoryPublicFeedCachePort::class, BlogCategoryPublicFeedCache::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->registerWebRoutes();
        $this->registerApiRoutes();
    }

    /**
     * `landing-public` guards the unauthenticated landing-page feeds (blog
     * categories + posts). Read-only anonymous traffic, keyed by IP — generous
     * enough for a browsing session, tight enough to blunt scraping
     * (OWASP §14 / §15.5). Shared with the Post module's public feed.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for(
            'landing-public',
            static fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->ip()),
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
