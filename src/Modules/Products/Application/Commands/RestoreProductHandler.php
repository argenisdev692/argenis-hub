<?php

declare(strict_types=1);

namespace Modules\Products\Application\Commands;

use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;

final readonly class RestoreProductHandler
{
    public function handle(string $uuid): bool
    {
        return (bool) ProductEloquentModel::onlyTrashed()->where('uuid', $uuid)->restore();
    }
}
