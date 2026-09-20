<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Modules\CvJobStudio\Application\DTOs\StudioSourceData;
use Modules\CvJobStudio\Domain\Ports\StudioCatalogReadPort;

/** The owner's discovery-source catalogue in resolution order. */
final readonly class ListSourcesHandler
{
    public function __construct(private StudioCatalogReadPort $catalog) {}

    /** @return list<StudioSourceData> */
    #[\NoDiscard]
    public function handle(int $userId): array
    {
        return $this->catalog->sources($userId);
    }
}
