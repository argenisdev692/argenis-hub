<?php

declare(strict_types=1);

namespace Modules\Products\Application\Commands;

use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Shared\Application\DTOs\BulkUuidsData;

final readonly class BulkDeleteProductsHandler
{
    public function handle(BulkUuidsData $data): int
    {
        return ProductEloquentModel::query()->whereIn('uuid', $data->uuids)->delete();
    }
}
