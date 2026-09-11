<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Domain\Exceptions\SourceUploadInvalidException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditStateConflictException;
use Modules\VideoEdits\Domain\Ports\VideoEditProcessingDispatcherPort;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Shared\Domain\Exceptions\StorageObjectNotFoundException;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\StoragePort;

/**
 * Verifies the uploaded objects and queues the edit (E3 · AD-2, AD-9, AD-15).
 *
 * One size lookup per source doubles as the existence check (Analyze A4). The
 * `draft → queued` move is an atomic compare-and-set, and the partial unique
 * index turns a second active edit into a conflict.
 */
final readonly class SubmitVideoEditHandler
{
    public function __construct(
        private VideoEditRepositoryPort $edits,
        private StoragePort $storage,
        private VideoEditProcessingDispatcherPort $processing,
        private AuditPort $audit,
        private Config $config,
    ) {}

    /**
     * @throws VideoEditNotFoundException
     * @throws VideoEditStateConflictException
     * @throws SourceUploadInvalidException
     */
    public function handle(string $uuid, Authenticatable $user): VideoEditEloquentModel
    {
        $userId = (int) $user->getAuthIdentifier();
        $edit = $this->edits->findOwnedWithDetails($uuid, $userId)
            ?? throw VideoEditNotFoundException::forUuid($uuid);

        if ($edit->status !== VideoEditStatus::Draft) {
            throw VideoEditStateConflictException::invalidState($edit->status);
        }

        $this->edits->recordVerifiedSourceSizes($this->verifiedSourceSizes($edit));

        $queued = $this->edits->transitionStatus($uuid, [VideoEditStatus::Draft], VideoEditStatus::Queued, [
            'queued_at' => CarbonImmutable::now(),
            'progress_percent' => 0,
        ]);

        if (! $queued) {
            throw VideoEditStateConflictException::invalidState(
                $this->edits->findByUuid($uuid)?->status ?? VideoEditStatus::Draft,
            );
        }

        $this->audit->log(
            'video_edit.submitted',
            null,
            ['edit_uuid' => $uuid, 'mode' => $edit->mode->value, 'source_count' => $edit->sources->count()],
            $user,
            'video-edits.video-edit',
        );

        $this->processing->dispatch($uuid);

        return $this->edits->findOwnedWithDetails($uuid, $userId)
            ?? throw VideoEditNotFoundException::forUuid($uuid);
    }

    /**
     * @return array<string, int> source uuid → stored size in bytes
     *
     * @throws SourceUploadInvalidException
     */
    private function verifiedSourceSizes(VideoEditEloquentModel $edit): array
    {
        $maxBytes = (int) $this->config->get('video-edit.limits.max_file_bytes');
        $tolerance = (float) $this->config->get('video-edit.limits.size_tolerance_ratio');
        $sizes = [];
        $errors = [];

        foreach ($edit->sources as $source) {
            try {
                $size = $this->storage->size((string) $source->storage_path);
            } catch (StorageObjectNotFoundException) {
                $errors[$source->uuid] = SourceUploadInvalidException::MISSING;

                continue;
            }

            $errors[$source->uuid] = match (true) {
                $size > $maxBytes => SourceUploadInvalidException::TOO_LARGE,
                abs($size - $source->declared_size_bytes) > $source->declared_size_bytes * $tolerance => SourceUploadInvalidException::SIZE_MISMATCH,
                default => null,
            };

            $sizes[$source->uuid] = $size;
        }

        $errors = array_filter($errors);

        if ($errors !== []) {
            throw SourceUploadInvalidException::forSources($errors);
        }

        return $sizes;
    }
}
