<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

/**
 * Read-only seam over `Modules\Cvs` (T-068, GAP-A1). This module never writes
 * to `cvs`; the CV module stays untouched.
 */
interface CvSourcePort
{
    /** @return array{cv_id: int, raw_text: string|null, is_primary: bool}|null */
    public function primaryForUser(int $userId): ?array;

    /** @return array{cv_id: int, raw_text: string|null, is_primary: bool}|null */
    public function findForUser(string $cvUuid, int $userId): ?array;
}
