<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use Modules\CvJobStudio\Domain\Exceptions\AllProvidersFailedException;

interface MatchNarratorPort
{
    /**
     * Narrates a stored score breakdown; never produces a number (NFR-2).
     *
     * @param  array{total: float, band: string|null, matches: list<array{requirement: string, credit: float}>}  $breakdown
     * @return array{why: string, gaps: string}
     *
     * @throws AllProvidersFailedException
     */
    public function narrate(array $breakdown, int $userId): array;
}
