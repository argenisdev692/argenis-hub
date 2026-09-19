<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\CvJobStudio\Domain\Exceptions\BudgetExceededException;
use Modules\CvJobStudio\Domain\Ports\SpendGuardPort;
use Modules\CvJobStudio\Domain\Services\PostingTextMinimiser;
use Modules\CvJobStudio\Infrastructure\Fetching\PostingFetchLadder;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingTextEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;

/** Pipeline stage 7 (conditional): extraction skipped when text is in hand. */
#[Tries(3)]
#[Timeout(600)]
#[Backoff([10, 60, 300])]
final class ExtractPostingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, MarksRunFailed, Queueable, SerializesModels;

    public function __construct(public int $runId, public int $userId)
    {
        $this->queue = 'studio';
    }

    public function handle(PostingFetchLadder $ladder, PostingTextMinimiser $minimiser, SpendGuardPort $spend): void
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
            ->chunkById(100, function ($postings) use ($ladder, $minimiser, $spend, $run, &$extracted): bool {
                foreach ($postings as $posting) {
                    // The Firecrawl step is paid: a spent `extraction` budget
                    // ends the stage instead of scraping unbounded (API4).
                    try {
                        $spend->ensure('extraction', $this->userId, $run->id);
                    } catch (BudgetExceededException) {
                        return false;
                    }

                    $result = $ladder->fetch($posting->canonical_url, false);

                    if ($result === null) {
                        continue;
                    }

                    if ($result['cost_micros'] > 0) {
                        $spend->record('extraction', $this->userId, $result['ladder_step'], 'scrape', $result['cost_micros'], true, $run->id);
                    }

                    // Fetched pages carry recruiter names/emails/phones: same
                    // minimisation as manual ingest and paste (T-149, NFR-8).
                    $text = $minimiser->minimise($result['text'], null);

                    StudioPostingTextEloquentModel::query()->create([
                        'user_id' => $this->userId,
                        'posting_id' => $posting->id,
                        'ladder_step' => $result['ladder_step'],
                        'completeness' => $result['completeness'],
                        'text' => $text,
                        'char_count' => mb_strlen($text),
                        'fetched_at' => now(),
                    ]);

                    $extracted++;
                }

                return true;
            });

        $run->update(['extracted_count' => $extracted, 'status' => 'extracted']);

        BuildInsightReportJob::dispatch($run->id, $this->userId);
    }
}
