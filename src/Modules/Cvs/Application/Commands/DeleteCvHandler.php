<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\Commands;

use Modules\Cvs\Domain\Exceptions\CvNotFoundException;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;

/**
 * An absent, foreign or already-suspended UUID is a 404 — never a silent no-op
 * that the controller would report back as "CV suspended." (OWASP §10).
 */
final readonly class DeleteCvHandler
{
    public function __construct(private CvRepositoryPort $cvs) {}

    /**
     * @throws CvNotFoundException
     */
    public function handle(string $uuid, int $userId): void
    {
        $this->cvs->softDelete($uuid, $userId) || throw new CvNotFoundException;
    }
}
