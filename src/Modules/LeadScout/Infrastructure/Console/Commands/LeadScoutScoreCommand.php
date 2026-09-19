<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\LeadScout\Application\Commands\ScoreCompanyHandler;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;

/**
 * Manual re-score pass (spec US-4, T034): one company or every company
 * with stored signals.
 */
final class LeadScoutScoreCommand extends Command
{
    protected $signature = 'lead-scout:score {--company= : Company uuid to re-score (default: all with signals)}';

    protected $description = 'Re-score companies deterministically from their stored signals';

    public function handle(ScoreCompanyHandler $score): int
    {
        $query = ScoutCompanyEloquentModel::query();

        if (is_string($this->option('company')) && $this->option('company') !== '') {
            $query->where('uuid', $this->option('company'));
        } else {
            $query->whereHas('signals');
        }

        $count = 0;

        foreach ($query->pluck('uuid') as $uuid) {
            $result = $score->handle($uuid);
            $this->line("{$uuid}: {$result->leadScore} ({$result->tier->value}, conf {$result->confidence})");
            $count++;
        }

        $this->info("Scored {$count} companie(s).");

        return self::SUCCESS;
    }
}
