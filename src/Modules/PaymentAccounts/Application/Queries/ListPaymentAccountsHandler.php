<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\PaymentAccounts\Application\DTOs\PaymentAccountData;
use Modules\PaymentAccounts\Application\DTOs\PaymentAccountFilterData;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;

final readonly class ListPaymentAccountsHandler
{
    public function handle(PaymentAccountFilterData $filters, int $perPage): LengthAwarePaginator
    {
        return PaymentAccountEloquentModel::query()
            ->applyFilters($filters)
            ->paginate($perPage)
            ->through(
                static fn (PaymentAccountEloquentModel $a): PaymentAccountData => PaymentAccountData::fromModel($a),
            );
    }
}
