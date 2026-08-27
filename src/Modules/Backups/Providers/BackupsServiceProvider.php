<?php

declare(strict_types=1);

namespace Modules\Backups\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Backups\Application\Contracts\DatabaseBackupRunner;
use Modules\Backups\Infrastructure\Archiving\ArtisanDatabaseBackupRunner;
use Modules\Backups\Infrastructure\Console\Commands\SyncBackupsCommand;
use Modules\Services\Providers\ServicesServiceProvider;

/**
 * Composition root for the (database-only) Backups module.
 *
 * No repository binding: the index is read straight off {@see
 * \Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel}
 * — one implementation, no decorator, no projection, every handler covered by
 * Feature tests hitting the real database. The Repository Optionality Rule's
 * SKIP criteria all hold, the same reasoning documented in
 * {@see ServicesServiceProvider}.
 *
 * The one bound port is {@see DatabaseBackupRunner}: it has a real second
 * implementation (the test fake) and isolates the `backup:run` shell-out so the
 * job is unit-testable without a database dumper.
 */
final class BackupsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DatabaseBackupRunner::class, ArtisanDatabaseBackupRunner::class);
    }

    public function boot(): void
    {
        $this->registerWebRoutes();
        $this->registerCommands();
    }

    private function registerWebRoutes(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SyncBackupsCommand::class]);
        }
    }
}
