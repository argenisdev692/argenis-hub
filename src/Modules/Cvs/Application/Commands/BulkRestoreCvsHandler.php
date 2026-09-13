<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\Commands;

use Modules\Cvs\Domain\Ports\CvRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

final readonly class BulkRestoreCvsHandler
{
    public function __construct(private CvRepositoryPort $cvs) {}

    public function handle(BulkUuidsData $data, int $userId): int
    {
        return $this->cvs->bulkRestoreForUser($data->uuids, $userId);
    }
}
