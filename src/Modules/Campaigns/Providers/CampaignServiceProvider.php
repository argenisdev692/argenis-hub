<?php

declare(strict_types=1);

namespace Modules\Campaigns\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Campaigns\Domain\Ports\CampaignAssetRendererPort;
use Modules\Campaigns\Domain\Ports\CampaignEvaluatorPort;
use Modules\Campaigns\Domain\Ports\CampaignGenerationDispatcherPort;
use Modules\Campaigns\Domain\Ports\CampaignGeneratorPort;
use Modules\Campaigns\Domain\Ports\CampaignIdeatorPort;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;
use Modules\Campaigns\Infrastructure\Ai\CampaignAssetRenderer;
use Modules\Campaigns\Infrastructure\Ai\LaravelAiCampaignAssistantAdapter;
use Modules\Campaigns\Infrastructure\Ai\LaravelAiCampaignEvaluatorAdapter;
use Modules\Campaigns\Infrastructure\Console\Commands\PublishScheduledCampaignsCommand;
use Modules\Campaigns\Infrastructure\Persistence\Repositories\EloquentCampaignRepository;
use Modules\Campaigns\Infrastructure\Queue\QueuedCampaignGenerationDispatcher;

final class CampaignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CampaignRepositoryPort::class, EloquentCampaignRepository::class);
        $this->app->bind(CampaignGenerationDispatcherPort::class, QueuedCampaignGenerationDispatcher::class);

        // Both TEXT ports resolve to the same adapter instance per request —
        // one Tavily research round-trip is shared if a caller ever needs
        // more than one during the same request (mirrors Post/SocialMedia).
        $this->app->singleton(LaravelAiCampaignAssistantAdapter::class);
        $this->app->bind(CampaignIdeatorPort::class, LaravelAiCampaignAssistantAdapter::class);
        $this->app->bind(CampaignGeneratorPort::class, LaravelAiCampaignAssistantAdapter::class);

        // The judge is a SEPARATE adapter on a separate provider on purpose:
        // one class writing and grading the same ad is not a quality gate.
        $this->app->bind(CampaignEvaluatorPort::class, LaravelAiCampaignEvaluatorAdapter::class);

        // Everything billed by the image/speech providers, invoked once on the
        // winning draft rather than on every discarded attempt.
        $this->app->bind(CampaignAssetRendererPort::class, CampaignAssetRenderer::class);
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Infrastructure/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PublishScheduledCampaignsCommand::class]);
        }
    }
}
