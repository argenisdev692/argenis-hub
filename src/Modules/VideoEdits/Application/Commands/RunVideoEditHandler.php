<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\VideoEdits\Application\Pipeline\VideoEditPipeline;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Domain\Ports\VideoEditWorkspacePort;

/**
 * Worker entry point for one attempt at an edit (T023 · AD-13, AD-14).
 *
 * Quietly does nothing when the edit was hard-deleted while queued (D14) or is
 * no longer runnable (a stale or duplicate job). The workspace is always wiped
 * afterwards, whatever happened (FR-18).
 */
final readonly class RunVideoEditHandler
{
    public function __construct(
        private VideoEditRepositoryPort $edits,
        private VideoEditPipeline $pipeline,
        private VideoEditWorkspacePort $workspace,
    ) {}

    public function handle(string $uuid, int $attempt): void
    {
        $edit = $this->edits->findByUuid($uuid);

        if ($edit === null) {
            return;
        }

        if ($edit->status === VideoEditStatus::Queued) {
            $started = $this->edits->transitionStatus($uuid, [VideoEditStatus::Queued], VideoEditStatus::Processing, [
                'started_at' => CarbonImmutable::now(),
                'attempts' => $attempt,
                'progress_percent' => 0,
                'current_stage' => ProcessingStage::Download,
            ]);

            if (! $started) {
                return;
            }
        } elseif ($edit->status === VideoEditStatus::Processing) {
            $this->edits->recordAttempt($uuid, $attempt);
        } else {
            return;
        }

        try {
            $detailed = $this->edits->findWithDetails($uuid);

            if ($detailed !== null) {
                $this->pipeline->run($detailed);
            }
        } finally {
            $this->workspace->wipe($uuid);
        }
    }
}
