<?php

declare(strict_types=1);

namespace Modules\Clients\Application\DTOs;

use Modules\Clients\Infrastructure\Http\Controllers\AdminClientController;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Canonical search + status + date-range + sort + pagination contract
 * (BACKEND-PHP §5.2). Consumed by {@see
 * \Modules\Clients\Application\Queries\ListClientsHandler} and the export
 * branch of {@see AdminClientController::export}
 * through `ClientEloquentModel::scopeApplyFilters()` — the single source both
 * the admin list and the export call. `status` here is the soft-delete axis
 * (`''` = all, `active`, `deleted`), not the CRM lifecycle column.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class ClientFilterData extends Data
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $status = null,
        #[Date, BeforeOrEqual('date_to')]
        public readonly ?string $dateFrom = null,
        #[Date, AfterOrEqual('date_from')]
        public readonly ?string $dateTo = null,
        public readonly string $sortField = 'created_at',
        #[In([1, -1])]
        public readonly int $sortOrder = -1,
        #[Min(1)]
        public readonly int $page = 1,
        #[Min(1), Max(100)]
        public readonly int $perPage = 15,
    ) {}
}
