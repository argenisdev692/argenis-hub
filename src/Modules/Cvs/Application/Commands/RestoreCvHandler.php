<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\Commands;

use Modules\Cvs\Domain\Exceptions\CvNotFoundException;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;

/**
 * Only the owner's suspended CVs are restorable; any other UUID is a 404
 * rather than a false "CV restored." (OWASP §10).
 */
final readonly class RestoreCvHandler
{
    public function __construct(private CvRepositoryPort $cvs) {}

    /**
     * @throws CvNotFoundException
     */
    public function handle(string $uuid, int $userId): void
    {
        $this->cvs->restore($uuid, $userId) || throw new CvNotFoundException;
    }
}
