<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\CvJobStudio\Domain\Services\GateEvaluator;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioGateResultEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;

/** Pipeline stage 5: G1/G2/G3 over run postings; pass AND fail stored. */
final class GatePostingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $runId, public int $userId)
    {
        $this->queue = 'studio';
    }

    public function handle(GateEvaluator $gates): void
    {
        $run = StudioRunEloquentModel::query()->ownedBy($this->userId)->with('profile')->where('id', $this->runId)->first();

        if ($run === null) {
            return;
        }

        $passed = 0;

        StudioPostingEloquentModel::query()
            ->ownedBy($this->userId)
            ->where('profile_id', $run->profile_id)
            ->chunkById(200, function ($postings) use ($run, $gates, &$passed): void {
                foreach ($postings as $posting) {
                    $verdicts = $gates->evaluate(
                        [
                            'remote_scope' => $posting->remote_scope ?? 'remote_unclear',
                            'title' => $posting->title,
                            'text' => (string) $posting->texts()->orderByDesc('id')->first()?->text,
                            'url' => $posting->canonical_url,
                        ],
                        [
                            'accepted_remote_scopes' => $run->profile->accepted_remote_scopes ?? [],
                            'stack_must' => $run->profile->stack_must ?? [],
                            'stack_reject' => $run->profile->stack_reject ?? [],
                        ],
                    );

                    $postingPassed = true;

                    foreach ($verdicts as $verdict) {
                        StudioGateResultEloquentModel::query()->updateOrCreate(
                            ['posting_id' => $posting->id, 'gate_code' => $verdict->gate->value],
                            [
                                'user_id' => $this->userId,
                                'passed' => $verdict->passed,
                                'reason_code' => $verdict->reasonCode,
                                'detail' => $verdict->detail,
                            ],
                        );

                        $postingPassed = $postingPassed && $verdict->passed;
                    }

                    if ($postingPassed) {
                        $passed++;
                    }
                }
            });

        $run->update(['gate_passed_count' => $passed]);

        ShortlistPostingsJob::dispatch($run->id, $this->userId);
    }
}
