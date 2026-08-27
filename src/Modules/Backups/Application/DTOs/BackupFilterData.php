<?php

declare(strict_types=1);

namespace Modules\Backups\Application\DTOs;

use Modules\Backups\Application\Queries\ListBackupsHandler;
use Modules\Backups\Infrastructure\Http\Controllers\AdminBackupController;
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
 * Search + status + date-range + sort + pagination contract shared by the admin
 * list ({@see ListBackupsHandler}) and the
 * export ({@see AdminBackupController::export})
 * through `BackupEloquentModel::scopeApplyFilters()` — one validated shape, no
 * duplicated `->when()` chains.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class BackupFilterData extends Data
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
