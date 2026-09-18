<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\LeadScout\Infrastructure\Queue\DiscoverAgenciesJob;

/**
 * Weekly discovery dispatch (spec US-7, T044).
 */
final class LeadScoutDiscoverCommand extends Command
{
    protected $signature = 'lead-scout:discover {--wave= : Wave key (wave1|wave2|wave3)} {--country= : ISO country filter} {--family= : Query family substring}';

    protected $description = 'Discover agencies without vacancies across the three waves';

    public function handle(): int
    {
        $wave = $this->stringOption('wave');
        $country = $this->stringOption('country');
        $family = $this->stringOption('family');

        DiscoverAgenciesJob::dispatch($wave, $country, $family);

        $this->info('Discovery dispatched.');

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
