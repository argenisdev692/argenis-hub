<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Application\DTOs;

use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
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
 * `status` is the soft-delete axis shared by every admin list. `method` and
 * `currency` are the two axes that actually matter here: "show me every USD
 * rail" is the question this screen exists to answer.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class PaymentAccountFilterData extends Data
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $status = null,
        public readonly ?PaymentMethod $method = null,
        public readonly ?string $currency = null,
        #[Date, BeforeOrEqual('date_to')]
        public readonly ?string $dateFrom = null,
        #[Date, AfterOrEqual('date_from')]
        public readonly ?string $dateTo = null,
        #[In(['created_at', 'label', 'method', 'currency'])]
        public readonly string $sortField = 'created_at',
        #[In([1, -1])]
        public readonly int $sortOrder = -1,
        #[Min(1)]
        public readonly int $page = 1,
        #[Min(1), Max(100)]
        public readonly int $perPage = 15,
    ) {}
}
