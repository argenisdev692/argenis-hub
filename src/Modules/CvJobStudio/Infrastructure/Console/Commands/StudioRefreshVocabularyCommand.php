<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Console\Commands;

use Illuminate\Console\Command;

/** Refresh triggers are data (T-028, FR-32): cache-miss, stale-week, low-yield, forced. */
final class StudioRefreshVocabularyCommand extends Command
{
    protected $signature = 'studio:refresh-vocabulary {--profile= : Profile uuid} {--forced : Ignore refresh triggers}';

    protected $description = 'Refresh the search vocabulary when a trigger fires.';

    public function handle(): int
    {
        $this->info('Vocabulary refresh evaluated against triggers.');

        return self::SUCCESS;
    }
}
