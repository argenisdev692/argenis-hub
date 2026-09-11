<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Support;

use Illuminate\Support\Carbon;
use Modules\Invoices\Application\DTOs\InvoiceFilterData;

/**
 * Turns the filters an export was run with into the one line that belongs
 * under a report's heading.
 *
 * A printed invoice report is read away from the screen that produced it, and
 * "Invoices" alone cannot tell a reader whether they are holding every invoice
 * or only the unpaid ones from March. Stating the criteria on the page is the
 * difference between a report and a pile of rows — and it is the reason the
 * paid/unpaid filter had to reach the PDF, not just the table.
 *
 * Pure and static: it reads a DTO and returns a string, so it is unit-tested
 * without a request, a database or a rendered PDF.
 */
final readonly class InvoiceFilterSummary
{
    /** Shown when no filter narrowed the export at all. */
    private const string UNFILTERED = 'All issued invoices.';

    #[\NoDiscard]
    public static function describe(InvoiceFilterData $filters): string
    {
        $parts = array_values(array_filter([
            self::describePaymentStatus($filters->paymentStatus),
            self::describeStatus($filters->status),
            self::describeYear($filters->year),
            self::describeDateRange($filters->dateFrom, $filters->dateTo),
            self::describeSearch($filters->search),
        ]));

        if ($parts === []) {
            return self::UNFILTERED;
        }

        return ucfirst(implode(' · ', $parts)).'.';
    }

    private static function describePaymentStatus(?string $paymentStatus): ?string
    {
        return match ($paymentStatus) {
            'paid' => 'paid invoices only',
            'unpaid' => 'unpaid invoices only',
            default => null,
        };
    }

    /**
     * `null` and `active` are the same result set (the SoftDeletes scope is on
     * either way), so neither is worth a line — only the two states that
     * actually change what the reader is holding are named.
     */
    private static function describeStatus(?string $status): ?string
    {
        return match ($status) {
            'suspended' => 'suspended only',
            'all' => 'including suspended',
            default => null,
        };
    }

    private static function describeYear(?int $year): ?string
    {
        return $year === null ? null : "year {$year}";
    }

    /**
     * Dates are re-formatted rather than echoed: the filter carries ISO
     * `YYYY-MM-DD`, and a report that says "March 1, 2026" beside a column of
     * dates in the same format reads as one document.
     */
    private static function describeDateRange(?string $from, ?string $to): ?string
    {
        $start = self::formatDate($from);
        $end = self::formatDate($to);

        return match (true) {
            $start !== null && $end !== null => "issued {$start} – {$end}",
            $start !== null => "issued from {$start}",
            $end !== null => "issued up to {$end}",
            default => null,
        };
    }

    private static function formatDate(?string $iso): ?string
    {
        if ($iso === null || trim($iso) === '') {
            return null;
        }

        try {
            return Carbon::parse($iso)->format('F j, Y');
        } catch (\Throwable) {
            // An unparseable bound is not worth failing an export over — the
            // rows are already correct, so the line simply omits it.
            return null;
        }
    }

    private static function describeSearch(?string $search): ?string
    {
        $term = trim((string) $search);

        return $term === '' ? null : "matching “{$term}”";
    }
}
