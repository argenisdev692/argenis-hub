<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Ports;

use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;

/**
 * Renders a single invoice PDF binary (portrait A4). Infrastructure adapter
 * owns DomPDF; Application only depends on this port.
 */
interface InvoicePdfRendererPort
{
    #[\NoDiscard]
    public function render(InvoiceEloquentModel $invoice): string;
}
