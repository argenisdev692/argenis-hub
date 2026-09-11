<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoices\Application\Support\InvoiceCrossBorderVatNotice;
use Modules\Invoices\Domain\Enums\InvoiceItemKind;
use Modules\Invoices\Domain\Enums\TaxMode;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceItemEloquentModel;

uses(RefreshDatabase::class);

/**
 * @param  list<InvoiceItemKind>|null  $lineKinds  null leaves the lines unloaded
 */
function exemptInvoice(?array $lineKinds = null): InvoiceEloquentModel
{
    $invoice = InvoiceEloquentModel::factory()->make(['tax_mode' => TaxMode::Exempt, 'tax_rate' => 0]);

    if ($lineKinds !== null) {
        $invoice->setRelation('items', new Collection(array_map(
            static fn (InvoiceItemKind $kind): InvoiceItemEloquentModel => new InvoiceItemEloquentModel(['kind' => $kind]),
            $lineKinds,
        )));
    }

    return $invoice;
}

it('writes the spanish notice for a schengen client', function (): void {
    $notice = InvoiceCrossBorderVatNotice::forExemptInvoice(exemptInvoice(), 'Portugal', 'Spain', 'ES');

    expect($notice)->toContain('Inversión del sujeto pasivo')
        ->toContain('Spain')
        ->not->toContain('Reverse Charge');
});

it('writes the english notice for a united states client', function (): void {
    $notice = InvoiceCrossBorderVatNotice::forExemptInvoice(exemptInvoice(), 'Portugal', 'United States', 'US');

    expect($notice)->toContain('VAT - Reverse Charge')->toContain('United States');
});

it('writes the portuguese notice for a portuguese client', function (): void {
    expect(InvoiceCrossBorderVatNotice::forExemptInvoice(exemptInvoice(), 'Portugal', 'Portugal', 'PT'))
        ->toContain('Autoliquidação');
});

it('returns no notice when a percentage above zero is charged', function (): void {
    $invoice = InvoiceEloquentModel::factory()->make(['tax_mode' => TaxMode::Percent, 'tax_rate' => 23]);

    expect(InvoiceCrossBorderVatNotice::forExemptInvoice($invoice, 'Portugal', 'Spain', 'ES'))->toBeNull();
});

it('omits the web development sentence on a training-only invoice', function (): void {
    $notice = InvoiceCrossBorderVatNotice::forExemptInvoice(
        exemptInvoice([InvoiceItemKind::Course, InvoiceItemKind::Video]),
        'Portugal',
        'Spain',
        'ES',
    );

    expect($notice)->toContain('Prestación de servicios transfronteriza')
        ->not->toContain('Desarrollo web');
});

it('keeps the web development sentence when a line bills other work', function (): void {
    $notice = InvoiceCrossBorderVatNotice::forExemptInvoice(
        exemptInvoice([InvoiceItemKind::Course, InvoiceItemKind::Service]),
        'Portugal',
        'United States',
        'US',
    );

    expect($notice)->toContain('Web development services provided remotely.');
});
