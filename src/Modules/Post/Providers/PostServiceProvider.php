<?php

declare(strict_types=1);

namespace Modules\Post\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Post\Domain\Ports\PostAiGenerationRepositoryPort;
use Modules\Post\Domain\Ports\PostContentEvaluatorPort;
use Modules\Post\Domain\Ports\PostContentGeneratorPort;
use Modules\Post\Domain\Ports\PostCoverImageRendererPort;
use Modules\Post\Domain\Ports\PostGenerationDispatcherPort;
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
use Modules\Post\Infrastructure\Persistence\Repositories\EloquentPostAiGenerationRepository;
use Modules\Post\Infrastructure\Persistence\Repositories\EloquentPostRepository;
use Modules\Post\Infrastructure\Queue\QueuedPostGenerationDispatcher;

final class PostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PostRepositoryPort::class, EloquentPostRepository::class);
        $this->app->bind(PostAiGenerationRepositoryPort::class, EloquentPostAiGenerationRepository::class);
        $this->app->bind(PostPublicFeedCachePort::class, PostPublicFeedCache::class);

        // Application never names the Job class — it depends on this port, and
        // only the adapter knows a queue exists (DIP).
        $this->app->bind(PostGenerationDispatcherPort::class, QueuedPostGenerationDispatcher::class);

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
        $this->configureRateLimiters();

        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Infrastructure/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PublishScheduledPostsCommand::class]);
        }
    }

    /**
     * NAMED limiters for the AI surface, and they have to be named.
     *
     * An anonymous `throttle:5,1` nested inside the group's anonymous
     * `throttle:60,1` builds its cache key from
     * `ThrottleRequests::resolveRequestSignature()` — the authenticated user's
     * id — and NOTHING else. Both middlewares therefore hit the SAME counter
     * on every request: one call consumed two of the five allowed generations,
     * and the endpoint answered 429 on the third, not the sixth. A named
     * limiter prefixes the key with the limiter's own name, so each gets its
     * own counter and the configured number is the number users actually get.
     *
     * Keyed by user id (never IP): these routes sit behind `auth`, and an
     * office behind one NAT address would otherwise share a single quota.
     */
    private function configureRateLimiters(): void
    {
        // Starts an up-to-5-iteration billed loop plus one image render.
        RateLimiter::for('post-ai-generate', fn (Request $request): Limit => $this->perUser($request, 5));

        // Single provider call each — topic ideation, social copy, reel package.
        RateLimiter::for('post-ai-assist', fn (Request $request): Limit => $this->perUser($request, 10));

        // Read-only poll. Generous: the wizard's interval is chosen to sit under it.
        RateLimiter::for('post-ai-status', fn (Request $request): Limit => $this->perUser($request, 30));
    }

    private function perUser(Request $request, int $attempts): Limit
    {
        return Limit::perMinute($attempts)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(static fn (Request $request, array $headers) => response(
                ['message' => __('Too many AI requests. Please slow down.')],
                429,
                $headers,
            ));
    }
}
