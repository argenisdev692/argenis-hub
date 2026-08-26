<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use Modules\Auth\Providers\AuthServiceProvider;
use Modules\Company\Providers\CompanyServiceProvider;
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
];
