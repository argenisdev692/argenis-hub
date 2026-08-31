<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Soft-deletes a set of content packages by UUID. Authorization
 * (permission:BULK_DELETE_SOCIAL_MEDIA) is enforced at the route.
 */
final readonly class BulkDeleteSocialMediaContentHandler
{
    public function __construct(private SocialMediaContentRepositoryPort $content) {}

    public function handle(BulkUuidsData $data): int
    {
        return DB::transaction(fn (): int => $this->content->bulkSoftDeleteByUuid($data->uuids));
    }
}
