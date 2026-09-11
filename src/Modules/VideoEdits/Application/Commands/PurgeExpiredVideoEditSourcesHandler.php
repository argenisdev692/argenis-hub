<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Shared\Domain\Ports\StoragePort;
use Throwable;

/**
 * Scheduled: deletes source recordings that are no longer needed (FR-9, FR-10).
 *
 * Covers failed edits whose 24 h retry window has closed and completed edits
 * whose sources could not be deleted at publish time. A file that fails to
 * delete keeps its path and is retried on the next run.
 */
final readonly class PurgeExpiredVideoEditSourcesHandler
{
    private const int BATCH = 100;

    public function __construct(
        private VideoEditRepositoryPort $edits,
        private StoragePort $storage,
    ) {}

    /**
     * @return int edits whose sources are now fully purged
     */
    public function handle(): int
    {
        $uuids = array_values(array_unique([
            ...$this->edits->failedWithExpiredSourcesUuids(CarbonImmutable::now(), self::BATCH),
            ...$this->edits->completedWithRetainedSourcesUuids(self::BATCH),
        ]));

        $fullyPurged = 0;

        foreach ($uuids as $uuid) {
            $edit = $this->edits->findWithDetails($uuid);

            if ($edit === null) {
                continue;
            }

            $retained = $edit->sources->whereNotNull('storage_path');
            $deleted = [];

            foreach ($retained as $source) {
                try {
                    $this->storage->delete((string) $source->storage_path);
                    $deleted[] = $source->uuid;
                } catch (Throwable) {
                    continue;
                }
            }

            $this->edits->markSourcesPurged($uuid, $deleted);

            if (count($deleted) === $retained->count()) {
                $fullyPurged++;
            }
        }

        return $fullyPurged;
    }
}
