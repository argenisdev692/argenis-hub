<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\ValueObjects\CvOption;
use Modules\LeadScout\Domain\ValueObjects\CvSnapshot;

/**
 * Read-only bridge to the Cvs module table (spec FR-1, research R14).
 * LeadScout never writes CVs and never copies their full text.
 */
interface CvSourcePort
{
    public function primaryMarkdownCv(int $userId): ?CvSnapshot;

    public function cvForUser(string $uuid, int $userId): ?CvSnapshot;

    /**
     * @return list<CvOption>
     */
    public function cvsForUser(int $userId): array;
}
