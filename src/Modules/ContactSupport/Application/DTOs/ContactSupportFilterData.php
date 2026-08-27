<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Application\DTOs;

use Modules\ContactSupport\Application\Queries\ListContactSupportsHandler;
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
 * (BACKEND-PHP §5.2), plus the two inbox toggles the `contact_supports` table
 * is indexed for: `readed` (read / unread) and `is_spam` (ham / spam). Consumed
 * by {@see ListContactSupportsHandler}
 * and the export branch of {@see
 * \Modules\ContactSupport\Infrastructure\Http\Controllers\AdminContactSupportController::export}
 * through `ContactSupportEloquentModel::scopeApplyFilters()`. `status` here is
 * the soft-delete axis (`''` = all, `active`, `deleted`).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class ContactSupportFilterData extends Data
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $status = null,
        public readonly ?bool $readed = null,
        public readonly ?bool $isSpam = null,
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
