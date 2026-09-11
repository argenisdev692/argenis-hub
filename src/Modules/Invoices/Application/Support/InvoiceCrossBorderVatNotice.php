<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Support;

use Modules\Invoices\Domain\Enums\TaxMode;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceItemEloquentModel;
use Shared\Support\EuSchengenCountries;

/**
 * B2B cross-border exempt VAT / reverse-charge boilerplate for the invoice PDF.
 * Portuguese for Portugal, Spanish for the rest of Schengen, English otherwise.
 *
 * The "web development provided remotely" sentence describes the service, not
 * the tax treatment, so it is only printed when the invoice bills something
 * other than catalog training: the issued course invoices in
 * `specs/INVOICE-MODULE/FACTURAS-MODELOS` carry no such line.
 */
final class InvoiceCrossBorderVatNotice
{
    public static function forExemptInvoice(
        InvoiceEloquentModel $invoice,
        string $issuerCountryName,
        ?string $clientCountryName,
        ?string $clientCountryCode,
    ): ?string {
        if (! self::isTaxExempt($invoice)) {
            return null;
        }

        $clientCode = strtoupper((string) ($clientCountryCode ?? ''));
        $clientLabel = self::clientCountryLabel($clientCode, $clientCountryName);
        $billsWebDevelopment = self::billsWebDevelopment($invoice);

        return match (true) {
            $clientCode === 'PT' => self::portugueseNotice($issuerCountryName, $clientLabel, $billsWebDevelopment),
            EuSchengenCountries::includes($clientCode) => self::spanishNotice($issuerCountryName, $clientLabel, $billsWebDevelopment),
            default => self::englishNotice($issuerCountryName, $clientLabel, $billsWebDevelopment),
        };
    }

    /**
     * Exempt when flagged so, or when a percentage mode carries a zero rate —
     * the PDF prints "Exempt" in both cases, so the notice must agree with it.
     */
    public static function isTaxExempt(InvoiceEloquentModel $invoice): bool
    {
        return $invoice->tax_mode === TaxMode::Exempt
            || (float) ($invoice->tax_rate ?? 0) === 0.0;
    }

    /**
     * False only when every loaded line bills a catalog product (course /
     * video). Unloaded or empty lines keep the sentence, the historical default.
     */
    private static function billsWebDevelopment(InvoiceEloquentModel $invoice): bool
    {
        if (! $invoice->relationLoaded('items') || $invoice->items->isEmpty()) {
            return true;
        }

        return ! $invoice->items->every(
            static fn (InvoiceItemEloquentModel $item): bool => $item->kind->requiresProduct(),
        );
    }

    private static function clientCountryLabel(string $clientCode, ?string $clientCountryName): string
    {
        if ($clientCountryName !== null && trim($clientCountryName) !== '') {
            return trim($clientCountryName);
        }

        return match ($clientCode) {
            'US' => 'United States',
            'PT' => 'Portugal',
            'ES' => 'Spain',
            default => $clientCode !== '' ? $clientCode : 'client country',
        };
    }

    private static function englishNotice(string $issuerCountryName, string $clientCountryLabel, bool $billsWebDevelopment): string
    {
        return "VAT - Reverse Charge: International transaction exempt from VAT. Cross-border service provision between {$issuerCountryName} and {$clientCountryLabel} (B2B)."
            .($billsWebDevelopment ? "\nWeb development services provided remotely." : '');
    }

    private static function spanishNotice(string $issuerCountryName, string $clientCountryLabel, bool $billsWebDevelopment): string
    {
        return "IVA - Inversión del sujeto pasivo: Transacción internacional exenta de IVA. Prestación de servicios transfronteriza entre {$issuerCountryName} y {$clientCountryLabel} (B2B)."
            .($billsWebDevelopment ? "\nDesarrollo web prestado de forma remota." : '');
    }

    private static function portugueseNotice(string $issuerCountryName, string $clientCountryLabel, bool $billsWebDevelopment): string
    {
        return "IVA - Autoliquidação: Transação internacional com IVA exempto. Prestação de serviços transfronteiriça entre {$issuerCountryName} e {$clientCountryLabel} (B2B)."
            .($billsWebDevelopment ? "\nServiços de desenvolvimento web prestados remotamente." : '');
    }
}
