<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\PaymentAccounts\Application\DTOs\StorePaymentAccountData;
use Modules\PaymentAccounts\Application\Support\DefaultPaymentAccount;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;

final readonly class UpdatePaymentAccountHandler
{
    #[\NoDiscard]
    public function handle(
        PaymentAccountEloquentModel $account,
        StorePaymentAccountData $data,
    ): PaymentAccountEloquentModel {
        return DB::transaction(function () use ($account, $data): PaymentAccountEloquentModel {
            $account->update([
                'method' => $data->method,
                'currency' => $data->currency,
                'label' => $data->label,
                'beneficiary' => $data->beneficiary,
                'bank_name' => $data->bankName,
                'iban' => $data->iban,
                'bic' => $data->bic,
                'account_number' => $data->accountNumber,
                'routing_number' => $data->routingNumber,
                'holder_email' => $data->holderEmail,
                'holder_phone' => $data->holderPhone,
                'instructions' => $data->instructions,
                'is_default' => $data->isDefault,
                'is_active' => $data->isActive,
                'sort_order' => $data->sortOrder,
            ]);

            $account->refresh();
            DefaultPaymentAccount::demoteSiblings($account);

            return $account;
        });
    }
}
