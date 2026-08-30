<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use Modules\ActivityLog\Providers\ActivityLogServiceProvider;
use Modules\Auth\Providers\AuthServiceProvider;
use Modules\Backups\Providers\BackupsServiceProvider;
use Modules\Blog\Providers\BlogServiceProvider;
use Modules\Clients\Providers\ClientsServiceProvider;
use Modules\Company\Providers\CompanyServiceProvider;
use Modules\ContactSupport\Providers\ContactSupportServiceProvider;
use Modules\Portfolios\Providers\PortfoliosServiceProvider;
use Modules\Post\Providers\PostServiceProvider;
use Modules\Services\Providers\ServicesServiceProvider;
use Shared\Providers\SharedServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    // Binds the TypeScript transformer config consumed by `php artisan typescript:transform`.
    TypeScriptTransformerServiceProvider::class,
    // Binds the cross-cutting Shared ports (AuditPort, StoragePort, …) the
    // modules depend on — must come before the module providers.
    SharedServiceProvider::class,
    AuthServiceProvider::class,
    CompanyServiceProvider::class,
    ServicesServiceProvider::class,
    ClientsServiceProvider::class,
    ContactSupportServiceProvider::class,
    PortfoliosServiceProvider::class,
    BlogServiceProvider::class,
    PostServiceProvider::class,
    ActivityLogServiceProvider::class,
    BackupsServiceProvider::class,
];
