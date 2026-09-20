<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Modules\CvJobStudio\Application\DTOs\StudioRunData;
use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioRunRepositoryPort;

/** One run's progress counters for the polled Runs page. */
final readonly class GetRunHandler
{
    public function __construct(private StudioRunRepositoryPort $runs) {}

    #[\NoDiscard]
    public function handle(string $uuid, int $userId): StudioRunData
    {
        return $this->runs->runForUser($uuid, $userId)
            ?? throw RecordNotFoundException::of('run', $uuid);
    }
}
