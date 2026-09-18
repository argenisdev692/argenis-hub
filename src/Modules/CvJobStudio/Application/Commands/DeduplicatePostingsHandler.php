<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;

/**
 * One posting, many sources (T-015, CHG-2): the same job from Greenhouse +
 * Arbeitnow + WWR + Tavily becomes 1 posting with 4 source rows. A fingerprint
 * collision attaches the cheapest/richest source as the text provider.
 */
final readonly class DeduplicatePostingsHandler
{
    public function __construct(private StudioPostingRepositoryPort $postings) {}

    #[\NoDiscard]
    public function handle(int $userId): int
    {
        return $this->postings->deduplicate($userId);
    }
}
