<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Queries;

use Modules\LeadScout\Domain\Entities\Source;
use Modules\LeadScout\Domain\Ports\SourceRepositoryPort;

/**
 * Job-source registry read (spec FR-2).
 */
final readonly class ListSourcesHandler
{
    public function __construct(private SourceRepositoryPort $sources) {}

    /**
     * @return list<Source>
     */
    public function handle(): array
    {
        return $this->sources->all();
    }
}
