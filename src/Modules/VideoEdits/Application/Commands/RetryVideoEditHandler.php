<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\VideoEdits\Application\DTOs\ManualRangeData;
use Modules\VideoEdits\Application\DTOs\RetryVideoEditData;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Domain\Exceptions\InvalidCutRangesException;
use Modules\VideoEdits\Domain\Exceptions\ManualRangesNotCorrectableException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditStateConflictException;
use Modules\VideoEdits\Domain\Ports\VideoEditProcessingDispatcherPort;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Re-queues a failed edit with its retained sources (E6 · US-8 · FR-11).
 *
 * Corrected manual ranges are accepted only when the edit failed because of
 * them (P1 · AD-16); they are re-validated against the real media when the job
 * starts.
 */
final readonly class RetryVideoEditHandler
{
    public function __construct(
        private VideoEditRepositoryPort $edits,
        private VideoEditProcessingDispatcherPort $processing,
        private AuditPort $audit,
    ) {}

    /**
     * @throws VideoEditNotFoundException
     * @throws VideoEditStateConflictException
     * @throws ManualRangesNotCorrectableException
     */
    public function handle(string $uuid, Authenticatable $user, RetryVideoEditData $data): VideoEditEloquentModel
    {
        $userId = (int) $user->getAuthIdentifier();
        $edit = $this->edits->findOwnedWithDetails($uuid, $userId)
            ?? throw VideoEditNotFoundException::forUuid($uuid);
        $now = CarbonImmutable::now();

        if (! $this->isRetryable($edit, $now)) {
            throw VideoEditStateConflictException::notRetryable();
        }

        $attributes = [
            'failure_code' => null,
            'failure_message' => null,
            'failure_details' => null,
            'failed_at' => null,
            'sources_expire_at' => null,
            'progress_percent' => 0,
            'current_stage' => null,
            'attempts' => 0,
            'queued_at' => $now,
        ];

        if ($data->manualRanges !== null) {
            if ($edit->failure_code !== InvalidCutRangesException::FAILURE_CODE || ! $edit->mode->acceptsCutDecisions()) {
                throw ManualRangesNotCorrectableException::create();
            }

            $attributes['parameters'] = [
                ...$edit->parameters,
                'manual_ranges' => array_map(
                    static fn (ManualRangeData $range): array => $range->toParameter(),
                    $data->manualRanges,
                ),
            ];
        }

        if (! $this->edits->transitionStatus($uuid, [VideoEditStatus::Failed], VideoEditStatus::Queued, $attributes)) {
            throw VideoEditStateConflictException::notRetryable();
        }

        $this->audit->log(
            'video_edit.retried',
            null,
            ['edit_uuid' => $uuid, 'ranges_corrected' => $data->manualRanges !== null],
            $user,
            'video-edits.video-edit',
        );

        $this->processing->dispatch($uuid);

        return $this->edits->findOwnedWithDetails($uuid, $userId)
            ?? throw VideoEditNotFoundException::forUuid($uuid);
    }

    private function isRetryable(VideoEditEloquentModel $edit, CarbonImmutable $now): bool
    {
        return $edit->status === VideoEditStatus::Failed
            && $edit->sources_purged_at === null
            && $edit->sources_expire_at?->isAfter($now) === true
            && $edit->sources->every(static fn ($source): bool => $source->storage_path !== null);
    }
}
