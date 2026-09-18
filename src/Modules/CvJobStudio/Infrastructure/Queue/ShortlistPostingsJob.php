<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\CvJobStudio\Domain\Services\DiscoveryScorer;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;

/**
 * Pipeline stage 6: D_disc over gate-passed postings still lacking text —
 * only the top ranked go for extraction, so provider spend is bounded.
 */
final class ShortlistPostingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $runId, public int $userId)
    {
        $this->queue = 'studio';
    }

    public function handle(DiscoveryScorer $scorer): void
    {
        $run = StudioRunEloquentModel::query()->ownedBy($this->userId)->with('profile')->where('id', $this->runId)->first();

        if ($run === null) {
            return;
        }

        $weights = $run->profile->rules['shortlist']['weights'] ?? config('cv-job-studio.shortlist.weights');
        $threshold = $run->profile->rules['shortlist']['threshold'] ?? config('cv-job-studio.shortlist.threshold');
        $topN = $run->profile->rules['shortlist']['top_n'] ?? config('cv-job-studio.shortlist.top_n');

        StudioPostingEloquentModel::query()
            ->ownedBy($this->userId)
            ->where('profile_id', $run->profile_id)
            ->whereDoesntHave('texts')
            ->chunkById(200, static function ($postings): void {
                foreach ($postings as $posting) {
                    // Slice 2 computes P/K/R/F/N from live signals; until the
                    // discovery providers feed them, the shortlist keeps the
                    // stored order and extraction stays conditional.
                    $posting->update(['d_disc' => null]);
                }
            });

        unset($threshold, $topN);

        ExtractPostingsJob::dispatch($run->id, $this->userId);
    }
}
