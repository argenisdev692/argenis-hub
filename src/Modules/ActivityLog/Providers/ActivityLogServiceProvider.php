<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\ActivityLog\Infrastructure\Console\Commands\ArchiveActivityLogsCommand;
use Modules\Services\Providers\ServicesServiceProvider;
use Spatie\Activitylog\Models\Activity;

/**
 * Composition root for the (read-only) ActivityLog module.
 *
 * No repository binding: the trail is read straight off the vendor
 * {@see Activity} model — one implementation, no
 * decorator, no projection, every handler covered by Feature tests hitting the
 * real database. The Repository Optionality Rule's SKIP criteria all hold, the
 * same reasoning documented in {@see ServicesServiceProvider}.
 */
final class ActivityLogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerWebRoutes();
        $this->registerApiRoutes();
        $this->registerCommands();
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

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ArchiveActivityLogsCommand::class]);
        }
    }
}
