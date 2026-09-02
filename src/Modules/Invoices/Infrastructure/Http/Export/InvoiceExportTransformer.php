<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Http\Export;

use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;

/**
 * Maps an invoice row to CSV / Excel / PDF columns (BACKEND-PHP §8).
 * Soft-delete Status is Active|Suspended — never Inactive.
 */
final readonly class InvoiceExportTransformer
{
    /**
     * @return array{Number: string, Client: string, Issue date: string, Due date: string, Total: string, Paid: string, Status: string}
     */
    #[\NoDiscard]
    public static function transformForExcel(InvoiceEloquentModel $invoice): array
    {
        return $invoice
            |> self::extractBaseData(...)
            |> self::formatDates(...)
            |> self::sanitizeOutput(...);
    }

    /**
     * @return array{Number: string, Client: string, Issue date: string, Due date: string, Total: string, Paid: string, Status: string}
     */
    #[\NoDiscard]
    public static function transformForPdf(InvoiceEloquentModel $invoice): array
    {
        return self::transformForExcel($invoice);
    }

    /**
     * @return array{Number: string, Client: string, Issue date: string, Due date: string, Total: string, Paid: string, Status: string}
     */
    private static function extractBaseData(InvoiceEloquentModel $invoice): array
    {
        return [
            'Number' => $invoice->invoice_number,
            'Client' => $invoice->client?->client_name ?? '—',
            'Issue date' => $invoice->issue_date?->toIso8601String() ?? '',
            'Due date' => $invoice->due_date?->toIso8601String() ?? '',
            'Total' => sprintf('%s %s', $invoice->currency, number_format((float) $invoice->total, 2, '.', '')),
            'Paid' => $invoice->is_paid ? 'Yes' : 'No',
            'Status' => $invoice->deleted_at !== null ? 'Suspended' : 'Active',
        ];
    }

    /**
     * @param  array{Number: string, Client: string, Issue date: string, Due date: string, Total: string, Paid: string, Status: string}  $data
     * @return array{Number: string, Client: string, Issue date: string, Due date: string, Total: string, Paid: string, Status: string}
     */
    private static function formatDates(array $data): array
    {
        foreach (['Issue date', 'Due date'] as $key) {
            if ($data[$key] === '') {
                continue;
            }

            try {
                $data[$key] = (new \DateTimeImmutable($data[$key]))->format('F j, Y');
            } catch (\Exception) {
                // Keep original value if parsing fails.
            }
        }

        return $data;
    }

    /**
     * @param  array{Number: string, Client: string, Issue date: string, Due date: string, Total: string, Paid: string, Status: string}  $data
     * @return array{Number: string, Client: string, Issue date: string, Due date: string, Total: string, Paid: string, Status: string}
     */
    private static function sanitizeOutput(array $data): array
    {
        return array_map(
            static fn (string $value): string => $value === '' ? '—' : $value,
            $data,
        );
    }
}
