<?php

declare(strict_types=1);

namespace Modules\Portfolios\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Portfolios\Application\DTOs\PortfolioData;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

final readonly class GetPortfolioHandler
{
    /**
     * @throws ModelNotFoundException<PortfolioEloquentModel>
     */
    #[\NoDiscard('handle() returns the portfolio.')]
    public function handle(string $uuid): PortfolioData
    {
        // withTrashed(): the admin detail view must also reach a suspended row —
        // it is the only way to preview one before restoring it.
        $portfolio = PortfolioEloquentModel::withTrashed()
            ->with('media:id,portfolio_id,path,sort_order')
            ->where('uuid', $uuid)
            ->firstOrFail();

        return PortfolioData::fromModel($portfolio);
    }
}
