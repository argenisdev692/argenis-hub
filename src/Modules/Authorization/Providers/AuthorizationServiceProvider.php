<?php

declare(strict_types=1);

namespace Modules\Authorization\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Authorization\Domain\Ports\PermissionRepositoryPort;
use Modules\Authorization\Domain\Ports\RoleRepositoryPort;
use Modules\Authorization\Infrastructure\Persistence\Repositories\EloquentPermissionRepository;
use Modules\Authorization\Infrastructure\Persistence\Repositories\EloquentRoleRepository;

/**
 * Composition root for the Authorization module.
 */
final class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RoleRepositoryPort::class, EloquentRoleRepository::class);
        $this->app->bind(PermissionRepositoryPort::class, EloquentPermissionRepository::class);
    }

    public function boot(): void
    {
        $this->registerWebRoutes();
        $this->registerApiRoutes();
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
