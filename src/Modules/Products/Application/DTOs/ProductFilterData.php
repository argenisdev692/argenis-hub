<?php

declare(strict_types=1);

namespace Modules\Products\Application\DTOs;

use Modules\Products\Domain\Enums\ProductStatus;
use Modules\Products\Domain\Enums\ProductType;
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
 * (`BACKEND-PHP/SKILL.md` §5.2).
 *
 * `status` is the row lifecycle (active / deleted) shared by every admin list;
 * `productStatus` is the catalog state (DRAFT / PUBLISHED / ARCHIVED). They are
 * different axes and both are filterable.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class ProductFilterData extends Data
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $status = null,
        public readonly ?ProductType $type = null,
        public readonly ?ProductStatus $productStatus = null,
        #[Date, BeforeOrEqual('date_to')]
        public readonly ?string $dateFrom = null,
        #[Date, AfterOrEqual('date_from')]
        public readonly ?string $dateTo = null,
        #[In(['created_at', 'title', 'price', 'start_date', 'status'])]
        public readonly string $sortField = 'created_at',
        #[In([1, -1])]
        public readonly int $sortOrder = -1,
        #[Min(1)]
        public readonly int $page = 1,
        #[Min(1), Max(100)]
        public readonly int $perPage = 15,
    ) {}
}
