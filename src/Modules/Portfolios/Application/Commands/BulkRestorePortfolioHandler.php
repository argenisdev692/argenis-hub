<?php

declare(strict_types=1);

namespace Modules\Portfolios\Application\Commands;

use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

final readonly class BulkRestorePortfolioHandler
{
    /**
     * Mass soft restore over a UUID set. Paired with
     * {@see BulkDeletePortfolioHandler}.
     *
     * @param  list<string>  $uuids
     */
    public function handle(array $uuids): int
    {
        return PortfolioEloquentModel::withTrashed()->whereIn('uuid', $uuids)->restore();
    }
}
