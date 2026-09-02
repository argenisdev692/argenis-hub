<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * No repository binding: the Repository Optionality Rule holds — one Eloquent
 * source, no decorator, no DB-free unit tests. Routes are the provider's only job.
 */
final class PaymentAccountsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
    }
}
