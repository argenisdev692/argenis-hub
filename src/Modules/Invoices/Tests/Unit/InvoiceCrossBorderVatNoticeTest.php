<?php

declare(strict_types=1);

namespace Modules\Invoices\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoices\Application\Support\InvoiceCrossBorderVatNotice;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Tests\TestCase;

final class InvoiceCrossBorderVatNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_spanish_notice_for_schengen_client(): void
    {
        $invoice = InvoiceEloquentModel::factory()->make([
            'tax_mode' => 'EXEMPT',
            'tax_rate' => 0,
        ]);

        $notice = InvoiceCrossBorderVatNotice::forExemptInvoice(
            $invoice,
            'Portugal',
            'PT',
            'Spain',
            'ES',
        );

        $this->assertNotNull($notice);
        $this->assertStringContainsString('Inversión del sujeto pasivo', $notice);
        $this->assertStringContainsString('Spain', $notice);
        $this->assertStringNotContainsString('Reverse Charge', $notice);
    }

    public function test_english_notice_for_united_states_client(): void
    {
        $invoice = InvoiceEloquentModel::factory()->make([
            'tax_mode' => 'EXEMPT',
            'tax_rate' => 0,
        ]);

        $notice = InvoiceCrossBorderVatNotice::forExemptInvoice(
            $invoice,
            'Portugal',
            'PT',
            'United States',
            'US',
        );

        $this->assertNotNull($notice);
        $this->assertStringContainsString('VAT - Reverse Charge', $notice);
        $this->assertStringContainsString('United States', $notice);
    }

    public function test_null_when_tax_is_percentage_above_zero(): void
    {
        $invoice = InvoiceEloquentModel::factory()->make([
            'tax_mode' => 'PERCENT',
            'tax_rate' => 23,
        ]);

        $this->assertNull(
            InvoiceCrossBorderVatNotice::forExemptInvoice($invoice, 'Portugal', 'PT', 'Spain', 'ES'),
        );
    }
}
