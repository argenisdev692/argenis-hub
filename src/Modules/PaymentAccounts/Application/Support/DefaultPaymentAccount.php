<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Application\Support;

use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;

/**
 * "Default" is scoped per currency, not globally: one default EUR account AND
 * one default USD account coexist, because they answer different questions.
 * Currency-agnostic accounts (`currency = null`) form their own bucket.
 */
final readonly class DefaultPaymentAccount
{
    /**
     * Clear the default flag on every sibling sharing this account's currency
     * bucket, so at most one default survives per bucket.
     */
    public static function demoteSiblings(PaymentAccountEloquentModel $account): void
    {
        if (! $account->is_default) {
            return;
        }

        PaymentAccountEloquentModel::query()
            ->whereKeyNot($account->id)
            ->where('user_id', $account->user_id)
            ->when(
                $account->currency === null,
                fn ($q) => $q->whereNull('currency'),
                fn ($q) => $q->where('currency', $account->currency),
            )
            ->update(['is_default' => false]);
    }
}
