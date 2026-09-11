<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands;

use Modules\Invoices\Domain\Ports\InvoicePdfCachePort;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

final readonly class BulkRestoreInvoicesHandler
{
    public function __construct(
        private InvoiceRepositoryPort $invoices,
        private InvoicePdfCachePort $pdfCache,
    ) {}

    public function handle(BulkUuidsData $data): int
    {
        $count = $this->invoices->bulkRestoreByUuid($data->uuids);

        $this->pdfCache->refresh(...$data->uuids);

        return $count;
    }
}
