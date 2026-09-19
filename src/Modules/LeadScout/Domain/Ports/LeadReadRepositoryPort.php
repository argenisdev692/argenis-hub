<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\ValueObjects\LeadCriteria;
use Modules\LeadScout\Domain\ValueObjects\LeadPage;

/**
 * The bandeja read (spec US-5 CA-1): tier A → B → C → discarded, then
 * score and confidence; unscored leads sink to the bottom.
 */
interface LeadReadRepositoryPort
{
    public function page(LeadCriteria $criteria, int $perPage): LeadPage;
}
