<?php

declare(strict_types=1);

namespace Modules\Invoices\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Application\Support\InvoicePdfViewAssembler;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Tests\TestCase;

final class InvoicePdfViewAssemblerTest extends TestCase
{
    use RefreshDatabase;

    public function test_spanish_labels_for_schengen_client_outside_portugal(): void
    {
        $client = ClientEloquentModel::factory()->make([
            'country' => 'Germany',
            'country_code' => 'DE',
        ]);
        $invoice = InvoiceEloquentModel::factory()->make([
            'tax_mode' => 'EXEMPT',
            'tax_rate' => 0,
        ]);
        $invoice->setRelation('client', $client);

        $assembler = new InvoicePdfViewAssembler;
        $pdf = $assembler->assemble($invoice, [
            'country' => 'Portugal',
            'country_code' => 'PT',
        ]);

        $this->assertSame('es', $pdf['html_lang']);
        $this->assertSame('FACTURA', $pdf['labels']['document_title']);
        $this->assertSame('Proveedor', $pdf['labels']['from']);
        $this->assertStringContainsString('Inversión del sujeto pasivo', (string) $pdf['notes_body']);
    }

    public function test_english_labels_for_united_states_client(): void
    {
        $client = ClientEloquentModel::factory()->make(['country_code' => 'US']);
        $invoice = InvoiceEloquentModel::factory()->make(['tax_mode' => 'EXEMPT']);
        $invoice->setRelation('client', $client);

        $assembler = new InvoicePdfViewAssembler;
        $pdf = $assembler->assemble($invoice, ['country' => 'Portugal', 'country_code' => 'PT']);

        $this->assertSame('en', $pdf['html_lang']);
        $this->assertSame('INVOICE', $pdf['labels']['document_title']);
        $this->assertStringContainsString('Reverse Charge', (string) $pdf['notes_body']);
    }

    public function test_currency_symbol_follows_invoice_currency(): void
    {
        $client = ClientEloquentModel::factory()->make(['country_code' => 'US']);
        $usdInvoice = InvoiceEloquentModel::factory()->make([
            'currency' => 'USD',
            'tax_mode' => 'EXEMPT',
        ]);
        $usdInvoice->setRelation('client', $client);

        $eurInvoice = InvoiceEloquentModel::factory()->make([
            'currency' => 'EUR',
            'tax_mode' => 'EXEMPT',
        ]);
        $eurInvoice->setRelation('client', $client);

        $assembler = new InvoicePdfViewAssembler;
        $company = ['country' => 'Portugal', 'country_code' => 'PT'];

        $this->assertSame('$', $assembler->assemble($usdInvoice, $company)['currency_symbol']);
        $this->assertSame('€', $assembler->assemble($eurInvoice, $company)['currency_symbol']);
    }
}
