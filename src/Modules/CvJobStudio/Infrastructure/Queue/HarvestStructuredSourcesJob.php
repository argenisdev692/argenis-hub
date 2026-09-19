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
use Modules\CvJobStudio\Application\Commands\IngestPostingHandler;
use Modules\CvJobStudio\Application\DTOs\IngestPostingData;
use Modules\CvJobStudio\Domain\Exceptions\BudgetExceededException;
use Modules\CvJobStudio\Domain\Ports\SpendGuardPort;
use Modules\CvJobStudio\Domain\Services\NeverFetchHostPolicy;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingSightingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSourceEloquentModel;
use Modules\CvJobStudio\Infrastructure\Sources\SourceResolver;

/**
 * Pipeline stage 1 (CHG-1, T-042): structured sources first — where they
 * already return full JD text, extraction is skipped entirely for those
 * postings. Walks `resolution_priority`, skips disabled/unhealthy/over-budget
 * sources, respects access modes. Idempotent on (run_id, posting_id);
 * counters and spend recorded on the run. `BudgetLedger::ensure()` runs
 * BEFORE each provider call (FR-33, SC-8).
 */
#[Tries(3)]
#[Timeout(600)]
#[Backoff([10, 60, 300])]
final class HarvestStructuredSourcesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, MarksRunFailed, Queueable, SerializesModels;

    public function __construct(public int $runId, public int $userId)
    {
        $this->queue = 'studio';
    }

    public function handle(SourceResolver $resolver, SpendGuardPort $spend, IngestPostingHandler $ingest, NeverFetchHostPolicy $access): void
    {
        $run = StudioRunEloquentModel::query()
            ->ownedBy($this->userId)
            ->with('profile')
            ->where('id', $this->runId)
            ->first();

        if ($run === null || $run->status !== 'queued') {
            return;
        }

        $run->update(['status' => 'harvesting', 'started_at' => now()]);

        $registry = StudioSourceEloquentModel::query()
            ->ownedBy($this->userId)
            ->orderBy('resolution_priority')
            ->get(['name', 'status', 'access_mode'])
            ->map(static fn ($source): array => [
                'name' => $source->name,
                'status' => $source->status,
                'access_mode' => $source->access_mode,
            ])
            ->all();

        $sourceIds = StudioSourceEloquentModel::query()->ownedBy($this->userId)->pluck('id', 'name')->all();
        $adapters = $resolver->adaptersFor($registry);
        $candidates = 0;
        $spendMicros = 0;

        foreach ($adapters as $name => $adapter) {
            $mode = collect($registry)->firstWhere('name', $name)['access_mode'] ?? 'link_only';

            if (! $access->mayFetch($mode)) {
                continue;
            }

            try {
                $spend->ensure('search', $this->userId, $run->id);
            } catch (BudgetExceededException) {
                break;
            }

            try {
                $harvest = $adapter->harvest($run->profile->slug, 20);
            } catch (\Throwable $exception) {
                $this->reportSkipped('harvest', $name, $exception);

                continue;
            }

            $spend->record('search', $this->userId, $name, 'harvest', $harvest['cost_micros'], true, $run->id);
            $spendMicros += $harvest['cost_micros'];

            foreach ($harvest['postings'] as $candidate) {
                if (($candidate['url'] ?? '') === '') {
                    continue;
                }

                try {
                    $posting = $ingest->handle(IngestPostingData::from([
                        'profile_uuid' => $run->profile->uuid,
                        'title' => $candidate['title'],
                        'canonical_url' => $candidate['url'],
                        'employer_name' => $candidate['employer'],
                        'location_text' => $candidate['location'],
                        'source' => $name,
                        'discovery_channel' => $candidate['discovery_channel'],
                        'text' => $candidate['full_text'] ?? $candidate['snippet'],
                    ]), $this->userId);

                    StudioPostingSightingEloquentModel::query()->create([
                        'user_id' => $this->userId,
                        'posting_id' => $posting->id,
                        'source_id' => $sourceIds[$name] ?? null,
                        'observed_at' => now(),
                    ]);

                    $candidates++;
                } catch (\Throwable $exception) {
                    $this->reportSkipped('ingest', $name, $exception);

                    continue;
                }
            }
        }

        $run->update([
            'status' => 'harvested',
            'candidates_count' => $candidates,
            'spend_micros' => $spendMicros,
        ]);

        CanonicalizeAndDedupeJob::dispatch($run->id, $this->userId);
    }

    /**
     * A dead source or a malformed candidate degrades the run instead of
     * killing it (NFR-6) — but never silently (OWASP A09). Class only: the
     * message may echo provider payloads or posting text.
     */
    private function reportSkipped(string $stage, string $source, \Throwable $exception): void
    {
        Log::warning('cv_studio.harvest.skipped', [
            'stage' => $stage,
            'source' => $source,
            'run_id' => $this->runId,
            'exception' => $exception::class,
        ]);
    }
}
