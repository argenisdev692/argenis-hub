<?php

declare(strict_types=1);

namespace Modules\Portfolios\Application\Commands;

use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

/**
 * Shared gallery-replacement step for {@see CreatePortfolioHandler} and
 * {@see UpdatePortfolioHandler}.
 *
 * The gallery has no independent lifecycle — the portfolio owns its whole media
 * set — so a write replaces it wholesale: drop every existing row, recreate
 * from the incoming ordered list of R2 object keys. `sort_order` is the array
 * index, so the client controls display order purely by list position.
 */
trait SyncsPortfolioMedia
{
    /**
     * @param  list<string>  $paths
     */
    private function syncMedia(PortfolioEloquentModel $portfolio, array $paths): void
    {
        $portfolio->media()->forceDelete();

        foreach (array_values($paths) as $index => $path) {
            $portfolio->media()->create([
                'path' => $path,
                'sort_order' => $index,
            ]);
        }

        $portfolio->load('media');
    }
}
