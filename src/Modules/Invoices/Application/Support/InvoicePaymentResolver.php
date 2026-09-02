<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Support;

use Modules\Invoices\Application\DTOs\InvoiceData;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;

/**
 * Turns the submitted payment selection into the columns the invoice stores.
 *
 * Two things matter here:
 *
 * 1. **Method and currency stay orthogonal.** A USD invoice may be settled by
 *    Remitly or by bank transfer; a EUR invoice likewise. Nothing infers the
 *    method from the currency — the account carries both, and an account with
 *    `currency = null` settles any currency.
 * 2. **The account is snapshotted, never referenced live.** An invoice is a
 *    legal document: changing an IBAN next year must not silently rewrite the
 *    PDF of an invoice already delivered. `payment_account_id` is kept for
 *    reporting, but `payment_details_json` is what the PDF renders.
 */
final readonly class InvoicePaymentResolver
{
    /**
     * @return array{
     *     payment_method: string|null,
     *     payment_account_id: int|null,
     *     payment_details_json: array<string, mixed>|null,
     *     transfer_number: string|null,
     *     payment_date: string|null,
     *     amount_received: float|null
     * }
     */
    #[\NoDiscard]
    public static function resolve(InvoiceData $data): array
    {
        $account = self::findAccount($data);

        return [
            'payment_method' => $data->paymentMethod?->value ?? $account?->method->value,
            'payment_account_id' => $account?->id,
            'payment_details_json' => $account?->toSnapshot(),
            'transfer_number' => $data->isPaid ? $data->transferNumber : null,
            'payment_date' => $data->isPaid ? $data->paymentDate : null,
            'amount_received' => $data->isPaid ? $data->amountReceived : null,
        ];
    }

    /**
     * The explicitly chosen account, or — when none was chosen — the default
     * account able to settle the invoice currency. An unpaid invoice still
     * resolves one: that is what prints the "how to pay me" block.
     */
    private static function findAccount(InvoiceData $data): ?PaymentAccountEloquentModel
    {
        if ($data->paymentAccountUuid !== null && $data->paymentAccountUuid !== '') {
            return PaymentAccountEloquentModel::query()
                ->where('uuid', $data->paymentAccountUuid)
                ->first();
        }

        return PaymentAccountEloquentModel::query()
            ->usableForCurrency($data->currency)
            ->when(
                $data->paymentMethod !== null,
                fn ($q) => $q->where('method', $data->paymentMethod),
            )
            ->first();
    }
}
