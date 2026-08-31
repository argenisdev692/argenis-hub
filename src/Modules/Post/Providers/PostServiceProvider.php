<?php

declare(strict_types=1);

namespace Modules\Post\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Post\Domain\Ports\PostContentEvaluatorPort;
use Modules\Post\Domain\Ports\PostContentGeneratorPort;
use Modules\Post\Domain\Ports\PostCoverImageRendererPort;
use Modules\Post\Domain\Ports\PostPublicFeedCachePort;
use Modules\Post\Domain\Ports\PostRepositoryPort;
use Modules\Post\Domain\Ports\PostTopicIdeatorPort;
use Modules\Post\Domain\Ports\ReelPackageGeneratorPort;
use Modules\Post\Domain\Ports\SocialCopyGeneratorPort;
use Modules\Post\Infrastructure\Ai\LaravelAiPostAssistantAdapter;
use Modules\Post\Infrastructure\Ai\LaravelAiPostEvaluatorAdapter;
use Modules\Post\Infrastructure\Ai\PostCoverImageRenderer;
use Modules\Post\Infrastructure\Cache\PostPublicFeedCache;
use Modules\Post\Infrastructure\Console\Commands\PublishScheduledPostsCommand;
use Modules\Post\Infrastructure\Persistence\Repositories\EloquentPostRepository;

final class PostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PostRepositoryPort::class, EloquentPostRepository::class);
        $this->app->bind(PostPublicFeedCachePort::class, PostPublicFeedCache::class);

        // The four TEXT ports resolve to the same adapter instance per request
        // — one Tavily research round-trip is shared if a caller ever needs
        // more than one during the same request.
        $this->app->singleton(LaravelAiPostAssistantAdapter::class);
        $this->app->bind(PostTopicIdeatorPort::class, LaravelAiPostAssistantAdapter::class);
        $this->app->bind(PostContentGeneratorPort::class, LaravelAiPostAssistantAdapter::class);
        $this->app->bind(SocialCopyGeneratorPort::class, LaravelAiPostAssistantAdapter::class);
        $this->app->bind(ReelPackageGeneratorPort::class, LaravelAiPostAssistantAdapter::class);

        // Scoring and artwork are deliberately NOT the writing adapter: the
        // judge runs on a different provider (an independent gate), and the
        // renderer is invoked once, after the loop, so rejected drafts cost no
        // images.
        $this->app->bind(PostContentEvaluatorPort::class, LaravelAiPostEvaluatorAdapter::class);
        $this->app->bind(PostCoverImageRendererPort::class, PostCoverImageRenderer::class);
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
