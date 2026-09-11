<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Application\Support\InvoicePdfViewAssembler;
use Modules\Invoices\Domain\Enums\TaxMode;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $invoiceAttributes
 * @return array<string, mixed>
 */
function assembledPdf(string $clientCountryCode, array $invoiceAttributes, ?string $clientCountry = null): array
{
    $invoice = InvoiceEloquentModel::factory()->make($invoiceAttributes);
    $invoice->setRelation('client', ClientEloquentModel::factory()->make([
        'country' => $clientCountry,
        'country_code' => $clientCountryCode,
    ]));

    return (new InvoicePdfViewAssembler)->assemble($invoice, ['country' => 'Portugal', 'country_code' => 'PT']);
}

it('uses spanish labels for a schengen client outside portugal', function (): void {
    $pdf = assembledPdf('DE', ['tax_mode' => TaxMode::Exempt, 'tax_rate' => 0], 'Germany');

    expect($pdf['html_lang'])->toBe('es')
        ->and($pdf['labels']['document_title'])->toBe('FACTURA')
        ->and($pdf['labels']['from'])->toBe('Proveedor')
        ->and((string) $pdf['notes_body'])->toContain('Inversión del sujeto pasivo');
});

it('uses english labels for a united states client', function (): void {
    $pdf = assembledPdf('US', ['tax_mode' => TaxMode::Exempt]);

    expect($pdf['html_lang'])->toBe('en')
        ->and($pdf['labels']['document_title'])->toBe('INVOICE')
        ->and((string) $pdf['notes_body'])->toContain('Reverse Charge');
});

it('follows the invoice currency for the symbol', function (string $currency, string $symbol): void {
    expect(assembledPdf('US', ['currency' => $currency, 'tax_mode' => TaxMode::Exempt])['currency_symbol'])
        ->toBe($symbol);
})->with([
    'usd' => ['USD', '$'],
    'eur' => ['EUR', '€'],
    'gbp' => ['GBP', '£'],
    // A legacy row outside the supported set prints its code, never `$`.
    'legacy chf' => ['CHF', 'CHF'],
]);

it('tells the template whether to print the tax as exempt', function (TaxMode $mode, int $rate, bool $exempt): void {
    expect(assembledPdf('US', ['tax_mode' => $mode, 'tax_rate' => $rate])['tax_exempt'])->toBe($exempt);
})->with([
    'exempt' => [TaxMode::Exempt, 0, true],
    'zero percent' => [TaxMode::Percent, 0, true],
    'twenty-three percent' => [TaxMode::Percent, 23, false],
]);
