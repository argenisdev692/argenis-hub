<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\SocialMedia\Domain\Ports\SocialMediaAssetRendererPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentEvaluatorPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentGeneratorPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaGenerationDispatcherPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaTopicIdeatorPort;
use Modules\SocialMedia\Infrastructure\Ai\LaravelAiSocialMediaAssistantAdapter;
use Modules\SocialMedia\Infrastructure\Ai\LaravelAiSocialMediaEvaluatorAdapter;
use Modules\SocialMedia\Infrastructure\Ai\SocialMediaAssetRenderer;
use Modules\SocialMedia\Infrastructure\Console\Commands\PublishScheduledSocialMediaContentCommand;
use Modules\SocialMedia\Infrastructure\Persistence\Repositories\EloquentSocialMediaContentRepository;
use Modules\SocialMedia\Infrastructure\Queue\QueuedSocialMediaGenerationDispatcher;

final class SocialMediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SocialMediaContentRepositoryPort::class, EloquentSocialMediaContentRepository::class);
        $this->app->bind(SocialMediaGenerationDispatcherPort::class, QueuedSocialMediaGenerationDispatcher::class);

        // Both TEXT ports resolve to the same adapter instance per request —
        // one Tavily research round-trip is shared if a caller ever needs
        // more than one during the same request (mirrors Post's adapter).
        $this->app->singleton(LaravelAiSocialMediaAssistantAdapter::class);
        $this->app->bind(SocialMediaTopicIdeatorPort::class, LaravelAiSocialMediaAssistantAdapter::class);
        $this->app->bind(SocialMediaContentGeneratorPort::class, LaravelAiSocialMediaAssistantAdapter::class);

        // Scoring and rendering are separate adapters on purpose: the judge
        // must run on a different model than the writer, and the renderer
        // must be callable once, after the loop, instead of per iteration.
        $this->app->bind(SocialMediaContentEvaluatorPort::class, LaravelAiSocialMediaEvaluatorAdapter::class);
        $this->app->bind(SocialMediaAssetRendererPort::class, SocialMediaAssetRenderer::class);
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Infrastructure/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PublishScheduledSocialMediaContentCommand::class]);
        }
    }
}
