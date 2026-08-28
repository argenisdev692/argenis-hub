<?php

declare(strict_types=1);

namespace Modules\Portfolios\Application\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

final readonly class DeletePortfolioHandler
{
    /**
     * @throws ModelNotFoundException<PortfolioEloquentModel>
     */
    public function handle(string $uuid): void
    {
        PortfolioEloquentModel::query()->where('uuid', $uuid)->firstOrFail()->delete();
    }
}
