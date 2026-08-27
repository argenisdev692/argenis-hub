<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * Composition root for the Contact Support module.
 *
 * No repository binding: {@see ContactSupportEloquentModel}
 * has exactly one implementation, no decorator, and every handler is covered by
 * Feature tests hitting the real database — the Repository Optionality Rule's
 * SKIP criteria all hold (`ARCHITECTURE-PHP/SKILL-SIMPLE-CRUD.md`).
 */
final class ContactSupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->registerWebRoutes();
        $this->registerApiRoutes();
    }

    /**
     * The public contact form is a mutating, unauthenticated endpoint, so it is
     * tight and keyed by IP (OWASP §14 / §15.5) — the same 5/min shape used for
     * `login` elsewhere in the app.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for(
            'public-contact-supports',
            static fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->ip()),
        );
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
