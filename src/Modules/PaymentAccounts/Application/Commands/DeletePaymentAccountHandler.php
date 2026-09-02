<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Application\Commands;

use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;

final readonly class DeletePaymentAccountHandler
{
    public function handle(string $uuid): bool
    {
        return (bool) PaymentAccountEloquentModel::query()->where('uuid', $uuid)->delete();
    }
}
