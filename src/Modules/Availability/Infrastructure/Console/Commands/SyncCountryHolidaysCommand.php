<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Console\Commands;

use App\Models\CompanyData;
use Illuminate\Console\Command;
use Modules\Availability\Domain\Services\HolidayMaterializer;

final class SyncCountryHolidaysCommand extends Command
{
    protected $signature = 'availability:sync-holidays
        {year? : Calendar year to sync}
        {--country= : ISO alpha-2 override; defaults to the company country}';

    protected $description = 'Materialise the company country holidays for a year into availability_exceptions.';

    public function handle(HolidayMaterializer $materializer): int
    {
        $country = $this->option('country')
            ?? CompanyData::query()->orderBy('id')->value('country_code');

        $years = $this->argument('year') !== null
            ? [(int) $this->argument('year')]
            : [now()->year, now()->year + 1];

        foreach ($years as $year) {
            $result = $materializer->materialize($year, $country);

            $this->info("[{$result['country']}] {$year}: {$result['created']} created, {$result['skipped']} skipped.");
        }

        return self::SUCCESS;
    }
}
