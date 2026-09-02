<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Application\Commands;

use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;
use Shared\Application\DTOs\BulkUuidsData;

final readonly class BulkRestorePaymentAccountsHandler
{
    public function handle(BulkUuidsData $data): int
    {
        return PaymentAccountEloquentModel::onlyTrashed()->whereIn('uuid', $data->uuids)->restore();
    }
}
