<?php

declare(strict_types=1);

namespace Modules\Availability\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Availability\Application\Listeners\ResyncHolidaysOnCountryChange;
use Modules\Availability\Domain\Ports\AvailabilityExceptionRepositoryPort;
use Modules\Availability\Domain\Ports\AvailabilityRuleRepositoryPort;
use Modules\Availability\Domain\Services\HolidayProviderRegistry;
use Modules\Availability\Domain\Services\PortugalHolidayProvider;
use Modules\Availability\Infrastructure\Console\Commands\SyncCountryHolidaysCommand;
use Modules\Availability\Infrastructure\Persistence\Repositories\EloquentAvailabilityExceptionRepository;
use Modules\Availability\Infrastructure\Persistence\Repositories\EloquentAvailabilityRuleRepository;
use Modules\Company\Domain\Events\CompanyCountryChanged;

final class AvailabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AvailabilityRuleRepositoryPort::class, EloquentAvailabilityRuleRepository::class);
        $this->app->bind(AvailabilityExceptionRepositoryPort::class, EloquentAvailabilityExceptionRepository::class);

        $this->app->singleton(HolidayProviderRegistry::class, fn ($app): HolidayProviderRegistry => new HolidayProviderRegistry([
            $app->make(PortugalHolidayProvider::class),
        ]));
    }

    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Infrastructure/Routes/api.php');

        // Rebuild national holidays when the company relocates. Registered
        // explicitly because attribute auto-discovery only scans app/Listeners.
        Event::listen(CompanyCountryChanged::class, [ResyncHolidaysOnCountryChange::class, 'handle']);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncCountryHolidaysCommand::class]);
        }
    }
}
