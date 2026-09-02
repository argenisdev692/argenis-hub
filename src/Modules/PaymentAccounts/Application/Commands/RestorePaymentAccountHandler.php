<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Application\Commands;

use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;

final readonly class RestorePaymentAccountHandler
{
    public function handle(string $uuid): bool
    {
        return (bool) PaymentAccountEloquentModel::onlyTrashed()->where('uuid', $uuid)->restore();
    }
}
