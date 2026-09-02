<?php

declare(strict_types=1);

namespace Modules\Products\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;

final readonly class GetProductHandler
{
    public function handle(string $uuid): ProductEloquentModel
    {
        return ProductEloquentModel::withTrashed()
            ->with('client:id,uuid,client_name')
            ->where('uuid', $uuid)
            ->first()
            ?? throw (new ModelNotFoundException)->setModel(ProductEloquentModel::class, [$uuid]);
    }
}
