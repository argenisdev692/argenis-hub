<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands;

use Modules\Invoices\Domain\Ports\InvoicePdfCachePort;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;

final readonly class DeleteInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryPort $invoices,
        private InvoicePdfCachePort $pdfCache,
    ) {}

    public function handle(string $uuid): void
    {
        $this->invoices->softDelete($uuid);

        $this->pdfCache->forget($uuid);
    }
}
