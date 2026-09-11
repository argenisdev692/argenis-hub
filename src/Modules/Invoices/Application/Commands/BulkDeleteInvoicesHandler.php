<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands;

use Modules\Invoices\Domain\Ports\InvoicePdfCachePort;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

final readonly class BulkDeleteInvoicesHandler
{
    public function __construct(
        private InvoiceRepositoryPort $invoices,
        private InvoicePdfCachePort $pdfCache,
    ) {}

    public function handle(BulkUuidsData $data): int
    {
        $count = $this->invoices->bulkSoftDeleteByUuid($data->uuids);

        $this->pdfCache->forget(...$data->uuids);

        return $count;
    }
}
