<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioApplicationEloquentModel;

/**
 * 21-day ageing for analysis only (T-081, FR-29): reports how many silent
 * applications aged past the window. The stored outcome value is never
 * overwritten with a guess.
 */
final class StudioAgeOutcomesCommand extends Command
{
    protected $signature = 'studio:age-outcomes';

    protected $description = 'Report aged-silent applications for analysis (never writes outcomes).';

    public function handle(): int
    {
        $count = StudioApplicationEloquentModel::query()
            ->where('outcome', 'unknown')
            ->where('created_at', '<=', now()->subDays(21))
            ->count();

        $this->info("{$count} application(s) aged past 21 days with no recorded outcome (analysis counts them as non-reply).");

        return self::SUCCESS;
    }
}
