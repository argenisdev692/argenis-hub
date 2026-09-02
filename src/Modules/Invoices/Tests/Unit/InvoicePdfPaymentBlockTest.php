<?php

declare(strict_types=1);

use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Application\Support\InvoicePdfViewAssembler;
use Modules\Invoices\Domain\Enums\InvoiceItemKind;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceItemEloquentModel;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Shared\Domain\Enums\BillingUnit;

/**
 * @param  list<BillingUnit>  $units
 */
function assembleFor(
    string $countryCode,
    array $units,
    ?PaymentMethod $method = null,
    ?array $snapshot = null,
    array $company = ['country' => 'Portugal', 'country_code' => 'PT'],
): array {
    $client = ClientEloquentModel::factory()->make(['country_code' => $countryCode]);
    $invoice = InvoiceEloquentModel::factory()->make([
        'currency' => $countryCode === 'US' ? 'USD' : 'EUR',
        'tax_mode' => 'EXEMPT',
        'payment_method' => $method,
        'payment_details_json' => $snapshot,
    ]);
    $invoice->setRelation('client', $client);
    $invoice->setRelation('items', collect($units)->map(
        static fn (BillingUnit $unit): InvoiceItemEloquentModel => new InvoiceItemEloquentModel([
            'kind' => InvoiceItemKind::Course,
            'unit' => $unit,
            'title' => 'Line',
            'quantity' => 1,
            'unit_price' => 1,
            'amount' => 1,
        ]),
    ));

    return (new InvoicePdfViewAssembler)->assemble($invoice, $company);
}

it('labels the price column per hour when the invoice bills time', function (): void {
    $pdf = assembleFor('ES', [BillingUnit::Hour]);

    expect($pdf['unit_price_heading'])->toBe('Precio/Hora')
        ->and($pdf['unit_labels'][BillingUnit::Hour->value])->toBe('horas');
});

it('falls back to a neutral price column on a mixed invoice', function (): void {
    // A course line plus a web-development line: neither heading fits both.
    $pdf = assembleFor('ES', [BillingUnit::Hour, BillingUnit::Unit]);

    expect($pdf['unit_price_heading'])->toBe('Precio unitario');
});

it('uses the english price column for a us client', function (): void {
    $pdf = assembleFor('US', [BillingUnit::Hour]);

    expect($pdf['unit_price_heading'])->toBe('Price/Hour')
        ->and($pdf['unit_labels'][BillingUnit::Hour->value])->toBe('hours');
});

it('translates the payment method into the document locale', function (): void {
    $es = assembleFor('ES', [BillingUnit::Hour], PaymentMethod::BankTransfer);
    $en = assembleFor('US', [BillingUnit::Unit], PaymentMethod::Remitly);

    expect($es['payment_method_label'])->toBe('Transferencia bancaria')
        ->and($en['payment_method_label'])->toBe('Remitly (International Transfer)');
});

it('renders the snapshot rather than the live company bank block', function (): void {
    $pdf = assembleFor('US', [BillingUnit::Unit], PaymentMethod::Remitly, [
        'method' => 'REMITLY',
        'label' => 'Remitly USD',
        'holder_email' => 'argenis692@gmail.com',
        'holder_phone' => '+351963490414',
    ], [
        'country' => 'Portugal',
        'country_code' => 'PT',
        'bank_iban' => 'PT50003600119910006305349',
        'bank_beneficiary' => 'Argenis Jose Carrillo Gonzalez',
    ]);

    $values = array_column($pdf['payment_details'], 'value');

    expect($values)->toContain('argenis692@gmail.com')
        ->and($values)->not->toContain('PT50003600119910006305349');
});

it('falls back to the company bank block for a pre-snapshot invoice', function (): void {
    $pdf = assembleFor('ES', [BillingUnit::Hour], null, null, [
        'country' => 'Portugal',
        'country_code' => 'PT',
        'bank_iban' => 'PT50003600119910006305349',
        'bank_beneficiary' => 'Argenis Jose Carrillo Gonzalez',
        'bank_bic' => 'MPIOPTPL',
        'bank_name' => 'Montepio',
    ]);

    $values = array_column($pdf['payment_details'], 'value');

    expect($values)->toContain('PT50003600119910006305349')
        ->and($values)->toContain('Montepio')
        ->and($pdf['payment_method_label'])->toBeNull();
});

it('emits no payment rows when there is nothing to print', function (): void {
    expect(assembleFor('ES', [BillingUnit::Hour])['payment_details'])->toBe([]);
});
