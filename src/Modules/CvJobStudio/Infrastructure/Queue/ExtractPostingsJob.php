<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\CvJobStudio\Infrastructure\Fetching\PostingFetchLadder;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingTextEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;

/** Pipeline stage 7 (conditional): extraction skipped when text is in hand. */
final class ExtractPostingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $runId, public int $userId)
    {
        $this->queue = 'studio';
    }

    public function handle(PostingFetchLadder $ladder): void
    {
        $run = StudioRunEloquentModel::query()->ownedBy($this->userId)->where('id', $this->runId)->first();

        if ($run === null) {
            return;
        }

        $extracted = 0;

        StudioPostingEloquentModel::query()
            ->ownedBy($this->userId)
            ->where('profile_id', $run->profile_id)
            ->whereDoesntHave('texts')
            ->chunkById(100, function ($postings) use ($ladder, &$extracted): void {
                foreach ($postings as $posting) {
                    $result = $ladder->fetch($posting->canonical_url, false);

                    if ($result === null) {
                        continue;
                    }

                    StudioPostingTextEloquentModel::query()->create([
                        'user_id' => $this->userId,
                        'posting_id' => $posting->id,
                        'ladder_step' => $result['ladder_step'],
                        'completeness' => $result['completeness'],
                        'text' => $result['text'],
                        'char_count' => mb_strlen($result['text']),
                        'fetched_at' => now(),
                    ]);

                    $extracted++;
                }
            });

        $run->update(['extracted_count' => $extracted, 'status' => 'extracted']);

        BuildInsightReportJob::dispatch($run->id, $this->userId);
    }
}
