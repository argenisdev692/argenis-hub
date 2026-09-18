<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;

/**
 * Walks `resolution_priority`, skipping disabled/unhealthy/over-budget
 * sources and anything whose access mode forbids fetching (T-042, CHG-4).
 * Names no provider in code — the registry decides.
 */
final readonly class SourceResolver
{
    /** @param  iterable<PostingSourcePort>  $sources */
    public function __construct(private iterable $sources) {}

    /**
     * @param  list<array<string, mixed>>  $registry  enabled sources ordered by resolution_priority
     * @return array<string, PostingSourcePort> keyed by source name
     */
    #[\NoDiscard]
    public function adaptersFor(array $registry): array
    {
        $byName = [];

        foreach ($this->sources as $source) {
            $byName[$source->sourceName()] = $source;
        }

        $adapters = [];

        foreach ($registry as $entry) {
            if (($entry['status'] ?? '') !== 'active' || isset($adapters[$entry['name']])) {
                continue;
            }

            if (isset($byName[$entry['name']])) {
                $adapters[$entry['name']] = $byName[$entry['name']];
            }
        }

        return $adapters;
    }
}
