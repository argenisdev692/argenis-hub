<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands;

use Modules\Invoices\Application\DTOs\InvoiceData;
use Modules\Invoices\Application\Support\InvoiceRecordAssembler;
use Modules\Invoices\Domain\Ports\InvoicePdfCachePort;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;

final readonly class UpdateInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryPort $invoices,
        private InvoiceRecordAssembler $records,
        private InvoicePdfCachePort $pdfCache,
    ) {}

    public function handle(InvoiceEloquentModel $invoice, InvoiceData $data): InvoiceEloquentModel
    {
        $record = $this->records->assemble($data, $invoice->uuid);

        $updated = $this->invoices->updateWithItems($invoice, $record['attributes'], $record['items']);

        $this->pdfCache->refresh($updated->uuid);

        return $updated;
    }
}
