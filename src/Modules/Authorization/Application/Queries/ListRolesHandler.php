<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Authorization\Application\DTOs\RoleFilterData;
use Modules\Authorization\Domain\Ports\RoleRepositoryPort;

final readonly class ListRolesHandler
{
    public function __construct(private RoleRepositoryPort $roles) {}

    public function handle(RoleFilterData $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->roles->paginate($filters, $perPage);
    }
}
