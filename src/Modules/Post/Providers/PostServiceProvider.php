<?php

declare(strict_types=1);

namespace Modules\Post\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Post\Domain\Ports\PostContentGeneratorPort;
use Modules\Post\Domain\Ports\PostPublicFeedCachePort;
use Modules\Post\Domain\Ports\PostRepositoryPort;
use Modules\Post\Domain\Ports\PostTopicIdeatorPort;
use Modules\Post\Domain\Ports\ReelPackageGeneratorPort;
use Modules\Post\Domain\Ports\SocialCopyGeneratorPort;
use Modules\Post\Infrastructure\Ai\LaravelAiPostAssistantAdapter;
use Modules\Post\Infrastructure\Cache\PostPublicFeedCache;
use Modules\Post\Infrastructure\Console\Commands\PublishScheduledPostsCommand;
use Modules\Post\Infrastructure\Persistence\Repositories\EloquentPostRepository;

final class PostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PostRepositoryPort::class, EloquentPostRepository::class);
        $this->app->bind(PostPublicFeedCachePort::class, PostPublicFeedCache::class);

        // All four AI ports resolve to the same adapter instance per request —
        // one Tavily research round-trip is shared if a caller ever needs more
        // than one during the same request.
        $this->app->singleton(LaravelAiPostAssistantAdapter::class);
        $this->app->bind(PostTopicIdeatorPort::class, LaravelAiPostAssistantAdapter::class);
        $this->app->bind(PostContentGeneratorPort::class, LaravelAiPostAssistantAdapter::class);
        $this->app->bind(SocialCopyGeneratorPort::class, LaravelAiPostAssistantAdapter::class);
        $this->app->bind(ReelPackageGeneratorPort::class, LaravelAiPostAssistantAdapter::class);
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Infrastructure/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PublishScheduledPostsCommand::class]);
        }
    }
}
