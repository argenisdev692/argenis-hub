<?php

declare(strict_types=1);

namespace Modules\Availability\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Availability\Domain\Ports\AvailabilityExceptionRepositoryPort;
use Modules\Availability\Domain\ValueObjects\ExceptionSource;

final readonly class HolidayMaterializer
{
    public function __construct(
        private HolidayProviderRegistry $registry,
        private AvailabilityExceptionRepositoryPort $exceptions,
    ) {}

    /**
     * @return array{country: string, created: int, skipped: int}
     */
    public function materialize(int $year, ?string $countryCode = null): array
    {
        $provider = $this->registry->forCountry($countryCode);

        $created = 0;
        $skipped = 0;

        foreach ($provider->forYear($year) as $date => $name) {
            if ($this->exceptions->findActiveByDate($date) !== null) {
                $skipped++;

                continue;
            }

            $this->exceptions->create([
                'date' => $date,
                'is_available' => false,
                'start_time' => null,
                'end_time' => null,
                'reason' => $name,
                'source' => ExceptionSource::Holiday,
            ]);

            $created++;
        }

        return [
            'country' => $provider->countryCode(),
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * Rebuild future national holidays for the current + next year. Purges only
     * holiday-sourced rows dated today-or-later (manual overrides are preserved,
     * past holidays kept as history), then re-materialises for $countryCode.
     * Called on a company country change.
     *
     * @return array{purged: int, created: int}
     */
    public function resync(?string $countryCode = null): array
    {
        $purged = $this->exceptions->purgeHolidaysFrom(CarbonImmutable::today()->toDateString());

        $created = 0;

        foreach ([CarbonImmutable::now()->year, CarbonImmutable::now()->year + 1] as $year) {
            $created += $this->materialize($year, $countryCode)['created'];
        }

        return ['purged' => $purged, 'created' => $created];
    }
}
