<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\PaymentAccounts\Application\DTOs\StorePaymentAccountData;
use Modules\PaymentAccounts\Application\Support\DefaultPaymentAccount;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;

final readonly class CreatePaymentAccountHandler
{
    #[\NoDiscard]
    public function handle(StorePaymentAccountData $data, int $userId): PaymentAccountEloquentModel
    {
        return DB::transaction(function () use ($data, $userId): PaymentAccountEloquentModel {
            $account = PaymentAccountEloquentModel::query()->create([
                'user_id' => $userId,
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

            DefaultPaymentAccount::demoteSiblings($account);

            return $account;
        });
    }
}
