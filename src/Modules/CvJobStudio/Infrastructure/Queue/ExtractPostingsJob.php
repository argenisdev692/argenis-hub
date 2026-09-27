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
use Illuminate\Support\Facades\Log;
use Modules\CvJobStudio\Application\Commands\ExtractRequirementsHandler;
use Modules\CvJobStudio\Domain\Exceptions\BudgetExceededException;
use Modules\CvJobStudio\Domain\Ports\SpendGuardPort;
use Modules\CvJobStudio\Domain\Services\GateEvaluator;
use Modules\CvJobStudio\Domain\Services\PostingTextMinimiser;
use Modules\CvJobStudio\Infrastructure\Fetching\PostingFetchLadder;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioGateResultEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingTextEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;

/**
 * Pipeline stage 7 (conditional): fetch skipped when text is in hand.
 * Requirement extraction rides this stage so the insight report's frequency
 * section reads every posting fetched — the extractor is hash-idempotent, so
 * re-runs and the backfill below never pay twice for the same text.
 */
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

    public function handle(
        PostingFetchLadder $ladder,
        PostingTextMinimiser $minimiser,
        SpendGuardPort $spend,
        ExtractRequirementsHandler $requirements,
        GateEvaluator $gates,
    ): void {
        $run = StudioRunEloquentModel::query()->ownedBy($this->userId)->with('profile')->where('id', $this->runId)->first();

        if ($run === null) {
            return;
        }

        $extracted = 0;

        $fetchDone = StudioPostingEloquentModel::query()
            ->ownedBy($this->userId)
            ->where('profile_id', $run->profile_id)
            ->whereDoesntHave('texts')
            ->chunkById(100, function ($postings) use ($ladder, $minimiser, $spend, $requirements, $run, &$extracted): bool {
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

                    if (! $this->extractRequirements($posting->uuid, $requirements)) {
                        return false;
                    }
                }

                return true;
            });

        // Backfill: texts stored by an earlier run (or left requirement-less
        // by a spent LLM budget) get their requirements now — the handler
        // skips texts whose hash already has rows, so this is fetch-free.
        if ($fetchDone !== false) {
            StudioPostingEloquentModel::query()
                ->ownedBy($this->userId)
                ->where('profile_id', $run->profile_id)
                ->whereHas('texts')
                ->whereDoesntHave('requirements')
                ->chunkById(100, function ($postings) use ($requirements): bool {
                    foreach ($postings as $posting) {
                        if (! $this->extractRequirements($posting->uuid, $requirements)) {
                            return false;
                        }
                    }

                    return true;
                });
        }

        // G5's authoritative pass. It is the only gate that needs the extracted
        // requirements, and it needs them AFTER they exist — so it cannot be
        // decided in the gate stage, where it can only pass provisionally.
        $this->evaluateSkillFloor($gates, $run);

        $run->update(['extracted_count' => $extracted, 'status' => 'extracted']);

        BuildInsightReportJob::dispatch($run->id, $this->userId);
    }

    /**
     * Record the skill-floor verdict for every posting that now has
     * requirements, then restate the run's gate tally.
     *
     * The recount is not cosmetic: `gate_passed_count` was written by the gate
     * stage, when G5 could only pass. Leaving it alone would keep reporting a
     * pass rate that includes postings this stage has just filtered out.
     */
    private function evaluateSkillFloor(GateEvaluator $gates, StudioRunEloquentModel $run): void
    {
        $profile = [
            'skill_floors' => $run->profile?->skill_floors ?? [],
            'stack_must' => $run->profile?->stack_must ?? [],
        ];

        StudioPostingEloquentModel::query()
            ->ownedBy($this->userId)
            ->where('profile_id', $run->profile_id)
            ->whereHas('requirements')
            ->with('requirements:id,posting_id,canonical_name,tag,nature')
            ->chunkById(200, function ($postings) use ($gates, $profile): void {
                foreach ($postings as $posting) {
                    $verdict = $gates->skillFloorVerdict(
                        [
                            'title' => $posting->title,
                            'url' => $posting->canonical_url,
                            'requirements' => $posting->requirements
                                ->map(static fn ($requirement): array => [
                                    'canonical_name' => $requirement->canonical_name,
                                    // The Eloquent model casts these to enums;
                                    // the gate's contract is the scalar shape
                                    // the repository stores.
                                    'tag' => $requirement->tag?->value,
                                    'nature' => $requirement->nature?->value,
                                ])->all(),
                        ],
                        $profile,
                    );

                    StudioGateResultEloquentModel::query()->updateOrCreate(
                        ['posting_id' => $posting->id, 'gate_code' => $verdict->gate->value],
                        [
                            'user_id' => $this->userId,
                            'passed' => $verdict->passed,
                            'reason_code' => $verdict->reasonCode,
                            'detail' => $verdict->detail,
                        ],
                    );
                }
            });

        $run->update([
            'gate_passed_count' => StudioPostingEloquentModel::query()
                ->ownedBy($this->userId)
                ->where('profile_id', $run->profile_id)
                ->whereDoesntHave('gateResults', static fn ($results) => $results->where('passed', false))
                ->count(),
        ]);
    }

    /**
     * One posting's requirements, best-effort: a dead extractor degrades the
     * posting instead of killing the run (NFR-6) — but never silently
     * (OWASP A09). Class only: the message may echo provider payloads or
     * posting text. A spent LLM budget stops the stage; the backfill above
     * resumes it on the next run once the budget resets.
     *
     * @return bool false when the stage must stop (budget spent).
     */
    private function extractRequirements(string $postingUuid, ExtractRequirementsHandler $requirements): bool
    {
        try {
            // Deliberately discarded: persistence is the effect; the rows
            // are read back by scoring and the insight report. The (void)
            // cast satisfies the handler's #[\NoDiscard] contract.
            (void) $requirements->handle($postingUuid, $this->userId);
        } catch (BudgetExceededException) {
            return false;
        } catch (\Throwable $exception) {
            Log::warning('cv_studio.requirements.skipped', [
                'posting_uuid' => $postingUuid,
                'run_id' => $this->runId,
                'exception' => $exception::class,
            ]);
        }

        return true;
    }
}
