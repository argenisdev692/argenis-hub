<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Support;

use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;

/**
 * Download filename for a single invoice PDF (portrait A4).
 *
 * Pattern: Invoice-{Client-Words}-{sequence}-{dd-mm-yyyy}.pdf
 * Example: Invoice-Aquashield-Restoration-LLC-015-01-08-2026.pdf
 */
final class InvoicePdfFilename
{
    public static function forInvoice(InvoiceEloquentModel $invoice): string
    {
        $invoice->loadMissing('client:id,client_name');
        $clientName = $invoice->client?->client_name ?? 'Client';
        $clientSegment = self::clientNameSegment($clientName);
        $numberSegment = sprintf('%03d', $invoice->sequence);
        $dateSegment = $invoice->issue_date->format('d-m-Y');

        return "Invoice-{$clientSegment}-{$numberSegment}-{$dateSegment}.pdf";
    }

    /**
     * Title-case each word; preserve acronyms (LLC, INC, …).
     */
    private static function clientNameSegment(string $clientName): string
    {
        $words = preg_split('/\s+/u', trim($clientName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $segments = array_map(static function (string $word): string {
            $normalized = strtoupper($word);
            if (in_array($normalized, ['LLC', 'INC', 'LTD', 'CO', 'LP', 'LLP'], true)) {
                return $normalized;
            }

            if (preg_match('/^[A-Z0-9]{2,}$/u', $word)) {
                return $word;
            }

            return mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }, $words);

        return implode('-', $segments);
    }
}
