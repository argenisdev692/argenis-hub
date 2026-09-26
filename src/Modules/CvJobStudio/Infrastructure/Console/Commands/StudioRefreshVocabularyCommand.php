<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\CvJobStudio\Application\Commands\RefreshVocabularyHandler;
use Modules\CvJobStudio\Domain\Services\VocabularyRefreshTrigger;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioVocabularyEloquentModel;

/**
 * Refresh triggers are data (T-028, FR-32): cache-miss, stale-week, low-yield,
 * forced.
 *
 * The command used to print a line and exit: it evaluated no trigger and called
 * no handler, so `studio_vocabulary` was never populated and discovery had no
 * search terms to work from. `--forced` is now the documented escape hatch over
 * the decision, and the decision itself still comes from
 * {@see VocabularyRefreshTrigger} — a scheduler needs no redesign to use this.
 */
final class StudioRefreshVocabularyCommand extends Command
{
    protected $signature = 'studio:refresh-vocabulary {--profile= : Profile uuid} {--forced : Ignore refresh triggers}';

    protected $description = 'Refresh the search vocabulary when a trigger fires.';

    public function handle(RefreshVocabularyHandler $handler): int
    {
        $profile = $this->resolveProfile();

        if ($profile === null) {
            return self::FAILURE;
        }

        $decision = (new VocabularyRefreshTrigger)->evaluate(
            $this->state($profile),
            (bool) $this->option('forced'),
        );

        if (! $decision['refresh']) {
            $this->info("Vocabulary is fresh (trigger: {$decision['trigger']}), nothing to do.");

            return self::SUCCESS;
        }

        $written = $handler->handle($profile->uuid, $profile->user_id);

        $this->info("Vocabulary refreshed for {$profile->slug}: {$written} terms (trigger: {$decision['trigger']}).");

        return self::SUCCESS;
    }

    private function resolveProfile(): ?StudioProfileEloquentModel
    {
        $uuid = $this->option('profile');

        $query = StudioProfileEloquentModel::query()->where('is_active', true);

        return is_string($uuid) && $uuid !== ''
            ? $query->where('uuid', $uuid)->first()
            : $query->orderBy('id')->first();
    }

    /**
     * @return array{terms_count: int, refreshed_at: string|null, last_yield: float|null}
     */
    private function state(StudioProfileEloquentModel $profile): array
    {
        $rows = StudioVocabularyEloquentModel::query()
            ->where('profile_id', $profile->id)
            ->get(['term', 'refreshed_at']);

        $refreshedAt = $rows
            ->map(static fn (StudioVocabularyEloquentModel $row): ?string => $row->refreshed_at?->toDateTimeString())
            ->filter()
            ->sortDesc()
            ->first();

        return [
            'terms_count' => $rows->count(),
            'refreshed_at' => $refreshedAt,
            // Yield is measured by the run pipeline, not here; until a run has
            // reported one the trigger must not fire on an absent value.
            'last_yield' => null,
        ];
    }
}
