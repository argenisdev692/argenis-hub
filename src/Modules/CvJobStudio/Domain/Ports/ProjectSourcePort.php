<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

/**
 * Read-only seam over `Modules\Portfolios` (T-008 contract): portfolio
 * projects map to `studio_cv_entries` with `kind = project`.
 */
interface ProjectSourcePort
{
    /** @return list<array{title: string, description: string|null, url: string|null}> */
    public function projectsForUser(int $userId): array;
}
