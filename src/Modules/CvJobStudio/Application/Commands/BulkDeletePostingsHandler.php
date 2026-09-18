<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

final readonly class BulkDeletePostingsHandler
{
    public function __construct(private StudioPostingRepositoryPort $postings) {}

    public function handle(BulkUuidsData $data, int $userId): int
    {
        return $this->postings->bulkSoftDeleteForUser($data->uuids, $userId);
    }
}
