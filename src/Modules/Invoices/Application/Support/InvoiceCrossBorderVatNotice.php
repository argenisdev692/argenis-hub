<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Support;

use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Shared\Support\EuSchengenCountries;

/**
 * B2B cross-border exempt VAT / reverse-charge boilerplate for PDF and form defaults.
 * Spanish for Schengen clients; English for United States; Portuguese for Portugal.
 */
final class InvoiceCrossBorderVatNotice
{
    public static function forExemptInvoice(
        InvoiceEloquentModel $invoice,
        string $issuerCountryName,
        string $issuerCountryCode,
        ?string $clientCountryName,
        ?string $clientCountryCode,
    ): ?string {
        if (! self::isTaxExempt($invoice)) {
            return null;
        }

        $clientCode = strtoupper((string) ($clientCountryCode ?? ''));
        $clientLabel = self::clientCountryLabel($clientCode, $clientCountryName);

        if ($clientCode === 'US') {
            return self::englishNotice($issuerCountryName, $clientLabel);
        }

        if ($clientCode === 'PT') {
            return self::portugueseNotice($issuerCountryName, $clientLabel);
        }

        if (EuSchengenCountries::includes($clientCode)) {
            return self::spanishNotice($issuerCountryName, $clientLabel);
        }

        return self::englishNotice($issuerCountryName, $clientLabel);
    }

    public static function forClientCountry(
        string $taxMode,
        ?float $taxRate,
        string $issuerCountryName,
        ?string $clientCountryName,
        ?string $clientCountryCode,
    ): ?string {
        if ($taxMode !== 'EXEMPT' && (float) ($taxRate ?? 0) !== 0.0) {
            return null;
        }

        $clientCode = strtoupper((string) ($clientCountryCode ?? ''));
        $clientLabel = self::clientCountryLabel($clientCode, $clientCountryName);

        if ($clientCode === 'US') {
            return self::englishNotice($issuerCountryName, $clientLabel);
        }

        if ($clientCode === 'PT') {
            return self::portugueseNotice($issuerCountryName, $clientLabel);
        }

        if (EuSchengenCountries::includes($clientCode)) {
            return self::spanishNotice($issuerCountryName, $clientLabel);
        }

        if ($clientCode === '') {
            return null;
        }

        return self::englishNotice($issuerCountryName, $clientLabel);
    }

    private static function isTaxExempt(InvoiceEloquentModel $invoice): bool
    {
        return $invoice->tax_mode === 'EXEMPT'
            || (float) ($invoice->tax_rate ?? 0) === 0.0;
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

    private static function englishNotice(string $issuerCountryName, string $clientCountryLabel): string
    {
        return "VAT - Reverse Charge: International transaction exempt from VAT. Cross-border service provision between {$issuerCountryName} and {$clientCountryLabel} (B2B).\nWeb development services provided remotely.";
    }

    private static function spanishNotice(string $issuerCountryName, string $clientCountryLabel): string
    {
        return "IVA - Inversión del sujeto pasivo: Transacción internacional exenta de IVA. Prestación de servicios transfronteriza entre {$issuerCountryName} y {$clientCountryLabel} (B2B).\nDesarrollo web prestado de forma remota.";
    }

    private static function portugueseNotice(string $issuerCountryName, string $clientCountryLabel): string
    {
        return "IVA - Autoliquidação: Transação internacional com IVA exempto. Prestação de serviços transfronteiriça entre {$issuerCountryName} e {$clientCountryLabel} (B2B).\nServiços de desenvolvimento web prestados remotamente.";
    }
}
