<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Restores a set of soft-deleted content packages by UUID. Authorization
 * (permission:BULK_RESTORE_SOCIAL_MEDIA) is enforced at the route.
 */
final readonly class BulkRestoreSocialMediaContentHandler
{
    public function __construct(private SocialMediaContentRepositoryPort $content) {}

    public function handle(BulkUuidsData $data): int
    {
        return DB::transaction(fn (): int => $this->content->bulkRestoreByUuid($data->uuids));
    }
}
