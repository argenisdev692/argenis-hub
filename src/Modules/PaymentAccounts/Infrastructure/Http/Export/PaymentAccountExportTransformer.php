<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Infrastructure\Http\Export;

use Modules\PaymentAccounts\Infrastructure\Http\Controllers\AdminPaymentAccountController;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;

/**
 * Maps a {@see PaymentAccountEloquentModel} row to export columns, shared by
 * the CSV, Excel and PDF branches of
 * {@see AdminPaymentAccountController::export} so all three stay identical.
 *
 * Settlement credentials are deliberately MASKED, never exported in full: this
 * spreadsheet leaves the application and lands in inboxes and shared drives, so
 * it carries enough to identify the rail and not enough to use it. Same
 * reasoning as the model's `logOnly([...])` allowlist.
 */
final readonly class PaymentAccountExportTransformer
{
    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function headers(): array
    {
        return ['Label', 'Method', 'Currency', 'Beneficiary', 'Bank', 'Account', 'Default', 'Active', 'Created', 'Status'];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function toRow(PaymentAccountEloquentModel $account): array
    {
        return $account
            |> self::extract(...)
            |> self::formatDates(...)
            |> self::sanitize(...);
    }

    /**
     * @return array<string, string|null>
     */
    private static function extract(PaymentAccountEloquentModel $account): array
    {
        return [
            'Label' => $account->label,
            'Method' => ucwords(strtolower(str_replace('_', ' ', $account->method->value))),
            'Currency' => $account->currency ?? 'Any',
            'Beneficiary' => $account->beneficiary,
            'Bank' => $account->bank_name,
            'Account' => self::mask($account->iban ?? $account->account_number),
            'Default' => $account->is_default ? 'Yes' : 'No',
            'Active' => $account->is_active ? 'Yes' : 'No',
            'Created' => $account->created_at?->toIso8601String(),
            'Status' => $account->deleted_at !== null ? 'Suspended' : 'Active',
        ];
    }

    /**
     * Keeps only the last four characters — enough to tell two rails apart,
     * useless to anyone who intercepts the file.
     */
    private static function mask(?string $identifier): ?string
    {
        $value = trim((string) $identifier);

        if ($value === '') {
            return null;
        }

        return mb_strlen($value) <= 4
            ? str_repeat('•', mb_strlen($value))
            : str_repeat('•', 4).' '.mb_substr($value, -4);
    }

    /**
     * ISO8601 → "March 3, 2026 14:05" (BACKEND-PHP §8 export date rule).
     *
     * @param  array<string, string|null>  $row
     * @return array<string, string|null>
     */
    private static function formatDates(array $row): array
    {
        if (is_string($row['Created']) && $row['Created'] !== '') {
            try {
                $row['Created'] = (new \DateTimeImmutable($row['Created']))->format('F j, Y H:i');
            } catch (\Exception) {
                // keep the original value when parsing fails
            }
        }

        return $row;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, string>
     */
    private static function sanitize(array $row): array
    {
        return array_map(
            static fn (?string $value): string => ($value === null || $value === '') ? '—' : $value,
            $row,
        );
    }
}
