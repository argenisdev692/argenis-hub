<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Cvs;

use Modules\CvJobStudio\Domain\Ports\ProjectSourcePort;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

/**
 * Read-only seam over `Modules\Portfolios` (T-008 contract): public projects
 * map to `studio_cv_entries` with `kind = project`. Read-only; the
 * Portfolios module is never modified.
 */
final readonly class EloquentPortfolioProjectSource implements ProjectSourcePort
{
    public function projectsForUser(int $userId): array
    {
        return PortfolioEloquentModel::query()
            ->where('user_id', $userId)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get(['title', 'description', 'live_url'])
            ->map(static fn ($portfolio): array => [
                'title' => $portfolio->title,
                'description' => $portfolio->description,
                'url' => $portfolio->live_url,
            ])
            ->all();
    }
}
