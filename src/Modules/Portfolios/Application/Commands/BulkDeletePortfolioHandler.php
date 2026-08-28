<?php

declare(strict_types=1);

namespace Modules\Portfolios\Application\Commands;

use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

final readonly class BulkDeletePortfolioHandler
{
    /**
     * Mass soft delete over a UUID set. Paired with
     * {@see BulkRestorePortfolioHandler} so a bulk selection is never a
     * one-way trip.
     *
     * @param  list<string>  $uuids
     */
    public function handle(array $uuids): int
    {
        return PortfolioEloquentModel::query()->whereIn('uuid', $uuids)->delete();
    }
}
