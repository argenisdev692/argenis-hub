<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Invoices\Application\Support\InvoicePdfViewAssembler;
use Modules\Invoices\Domain\Ports\InvoicePdfRendererPort;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Shared\Infrastructure\Company\CompanyProfile;

/**
 * DomPDF adapter for a single invoice document (BACKEND-PHP §8).
 */
final readonly class DomPdfInvoiceRenderer implements InvoicePdfRendererPort
{
    #[\NoDiscard]
    public function render(InvoiceEloquentModel $invoice): string
    {
        $invoice->loadMissing([
            'items',
            'client:id,uuid,client_name,email,phone,tax_id,nif,address,country,country_code',
            'product:id,uuid,title,type',
        ]);

        $company = array_merge(CompanyProfile::pdfBranding(), [
            'invoice_logo_data_uri' => CompanyProfile::invoiceLogoDataUri(),
        ]);

        $assembler = new InvoicePdfViewAssembler;

        return Pdf::loadView('exports.pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'pdf' => $assembler->assemble($invoice, $company),
        ])
            ->setPaper('a4', 'portrait')
            ->output();
    }
}
