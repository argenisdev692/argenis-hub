<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Authorization\Application\DTOs\PermissionFilterData;
use Modules\Authorization\Domain\Ports\PermissionRepositoryPort;

final readonly class ListPermissionsHandler
{
    public function __construct(private PermissionRepositoryPort $permissions) {}

    public function handle(PermissionFilterData $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->permissions->paginate($filters, $perPage);
    }
}
