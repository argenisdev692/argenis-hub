<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
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
