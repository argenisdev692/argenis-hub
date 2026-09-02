<?php

declare(strict_types=1);

namespace Modules\Products\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Products\Application\DTOs\ProductData;
use Modules\Products\Application\DTOs\ProductFilterData;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;

final readonly class ListProductsHandler
{
    public function handle(ProductFilterData $filters, int $perPage): LengthAwarePaginator
    {
        return ProductEloquentModel::query()
            ->with('client:id,uuid,client_name')
            ->applyFilters($filters)
            ->paginate($perPage)
            ->through(static fn (ProductEloquentModel $product): ProductData => ProductData::fromModel($product));
    }
}
