<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // BACKEND-PHP §4.1 #1 — `Model::shouldBeStrict()` bundles three flags;
        // only the mass-assignment one is safe to switch on today. The other two
        // are tracked as known deviations:
        //
        //  - `preventLazyLoading()` is incompatible with spatie/laravel-permission:
        //    PermissionRegistrar resolves `$role->permissions` lazily on every
        //    `hasPermissionTo()` / `can()` call, so the global switch throws on
        //    ordinary authorization checks rather than on real N+1s. N+1 detection
        //    in development is covered by beyondcode/laravel-query-detector.
        //  - `preventAccessingMissingAttributes()` surfaces pre-existing partial
        //    `select()` reads in the Auth, ActivityLog, Campaigns and Post modules
        //    (19 red tests). Enable it once those are fixed — it belongs to that
        //    remediation, not to a single module's audit.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Spec 001 FR-01: 12+ characters with mixed case, a number and a symbol —
        // enforced in EVERY environment, so a weak password can never be created
        // locally and then carried into production. Only the breach-corpus check
        // is production-only: it calls out to Have I Been Pwned, which must not
        // sit inside a test run or an offline development machine.
        Password::defaults(function (): Password {
            $policy = Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();

            return app()->isProduction() ? $policy->uncompromised() : $policy;
        });
    }
}
