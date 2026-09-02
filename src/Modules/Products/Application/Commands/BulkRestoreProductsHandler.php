<?php

declare(strict_types=1);

namespace Modules\Products\Application\Commands;

use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Shared\Application\DTOs\BulkUuidsData;

final readonly class BulkRestoreProductsHandler
{
    public function handle(BulkUuidsData $data): int
    {
        return ProductEloquentModel::onlyTrashed()->whereIn('uuid', $data->uuids)->restore();
    }
}
