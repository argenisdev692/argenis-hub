<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;

/**
 * A CV belonging to another user is reported as 404, never 403: confirming the
 * UUID exists would already leak that someone else holds it (OWASP §11).
 */
final readonly class GetCvHandler
{
    public function __construct(private CvRepositoryPort $cvs) {}

    #[\NoDiscard]
    public function handle(string $uuid, int $userId): CvEloquentModel
    {
        return $this->cvs->findByUuidForUser($uuid, $userId)
            ?? throw (new ModelNotFoundException)->setModel(CvEloquentModel::class, [$uuid]);
    }
}
