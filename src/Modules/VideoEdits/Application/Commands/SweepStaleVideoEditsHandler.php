<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Shared\Domain\Ports\StoragePort;

/**
 * Scheduled backstop (AD-14, D17):
 *  - an edit with no progress write for longer than the stale limit is marked
 *    `processing_timeout` (a crashed worker never calls `failed()`, and PCNTL
 *    timeouts do not exist on Windows);
 *  - a draft never submitted is deleted together with anything uploaded for it.
 */
final readonly class SweepStaleVideoEditsHandler
{
    private const int BATCH = 100;

    public function __construct(
        private VideoEditRepositoryPort $edits,
        private MarkVideoEditFailedHandler $failures,
        private StoragePort $storage,
        private Config $config,
    ) {}

    /**
     * @return array{timed_out: int, expired_drafts: int}
     */
    public function handle(): array
    {
        $now = CarbonImmutable::now();
        $timedOut = 0;
        $expiredDrafts = 0;

        $staleBefore = $now->subMinutes((int) $this->config->get('video-edit.retention.stale_processing_minutes'));

        foreach ($this->edits->staleProcessingUuids($staleBefore, self::BATCH) as $uuid) {
            $this->failures->handleTimeout($uuid);
            $timedOut++;
        }

        $draftsBefore = $now->subHours((int) $this->config->get('video-edit.retention.draft_hours'));

        foreach ($this->edits->expiredDraftUuids($draftsBefore, self::BATCH) as $uuid) {
            $deleted = $this->edits->deleteDraft($uuid, function (VideoEditEloquentModel $draft): void {
                foreach ($draft->sources->pluck('storage_path')->filter() as $path) {
                    $this->storage->delete((string) $path);
                }
            });

            if ($deleted) {
                $expiredDrafts++;
            }
        }

        return ['timed_out' => $timedOut, 'expired_drafts' => $expiredDrafts];
    }
}
