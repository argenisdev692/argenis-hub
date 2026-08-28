<?php

declare(strict_types=1);

namespace Modules\Portfolios\Application\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Portfolios\Application\DTOs\PortfolioData;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

final readonly class RestorePortfolioHandler
{
    /**
     * @throws ModelNotFoundException<PortfolioEloquentModel>
     */
    #[\NoDiscard('handle() returns the restored portfolio.')]
    public function handle(string $uuid): PortfolioData
    {
        $portfolio = PortfolioEloquentModel::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $portfolio->restore();

        return PortfolioData::fromModel($portfolio->load('media'));
    }
}
