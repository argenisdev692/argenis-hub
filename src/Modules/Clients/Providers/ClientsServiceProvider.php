<?php

declare(strict_types=1);

namespace Modules\Clients\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

/**
 * Composition root for the Clients module.
 *
 * No repository binding: {@see ClientEloquentModel}
 * has exactly one implementation, no decorator, and every handler is covered by
 * Feature tests hitting the real database — the Repository Optionality Rule's
 * SKIP criteria all hold (`ARCHITECTURE-PHP/SKILL-SIMPLE-CRUD.md`). The module
 * exposes no public API, so there are no API routes to mount.
 */
final class ClientsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
    }
}
