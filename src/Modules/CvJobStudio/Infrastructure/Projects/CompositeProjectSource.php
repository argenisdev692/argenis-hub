<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Projects;

use Modules\CvJobStudio\Domain\Ports\ProjectSourcePort;
use Modules\CvJobStudio\Infrastructure\Cvs\EloquentPortfolioProjectSource;

/**
 * Portfolio projects first, then GitHub repos, deduplicated by URL — the
 * parse step keeps its single `ProjectSourcePort` seam (T-008) while both
 * inventories feed `kind = project` entries.
 */
final readonly class CompositeProjectSource implements ProjectSourcePort
{
    public function __construct(
        private EloquentPortfolioProjectSource $portfolios,
        private GithubProjectSource $github,
    ) {}

    /** @return list<array{title: string, description: string|null, url: string|null}> */
    public function projectsForUser(int $userId): array
    {
        $merged = [...$this->portfolios->projectsForUser($userId), ...$this->github->projectsForUser($userId)];
        $seen = [];
        $projects = [];

        foreach ($merged as $project) {
            $key = mb_strtolower(trim((string) ($project['url'] ?? $project['title'])));

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $projects[] = $project;
        }

        return $projects;
    }
}
