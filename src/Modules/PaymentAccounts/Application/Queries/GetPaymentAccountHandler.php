<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;

final readonly class GetPaymentAccountHandler
{
    public function handle(string $uuid): PaymentAccountEloquentModel
    {
        return PaymentAccountEloquentModel::withTrashed()->where('uuid', $uuid)->first()
            ?? throw (new ModelNotFoundException)->setModel(PaymentAccountEloquentModel::class, [$uuid]);
    }
}
