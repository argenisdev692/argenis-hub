<?php

declare(strict_types=1);

namespace Modules\Products\Application\Commands;

use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;

final readonly class DeleteProductHandler
{
    public function handle(string $uuid): bool
    {
        return (bool) ProductEloquentModel::query()->where('uuid', $uuid)->delete();
    }
}
