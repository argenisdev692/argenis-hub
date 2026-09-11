<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands;

use Modules\Invoices\Application\DTOs\InvoiceData;
use Modules\Invoices\Application\Support\InvoiceRecordAssembler;
use Modules\Invoices\Domain\Ports\InvoicePdfCachePort;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;

final readonly class CreateInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryPort $invoices,
        private InvoiceRecordAssembler $records,
        private InvoicePdfCachePort $pdfCache,
    ) {}

    #[\NoDiscard]
    public function handle(InvoiceData $data, int $userId): InvoiceEloquentModel
    {
        $record = $this->records->assemble($data);

        $invoice = $this->invoices->createWithItems(
            ['user_id' => $userId, ...$record['attributes']],
            $record['items'],
        );

        $this->pdfCache->refresh($invoice->uuid);

        return $invoice;
    }
}
