<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;

/**
 * Single-record lookup. Always loads fresh from the repository so edit forms and
 * PDF generation never read a stale/serialized Eloquent snapshot (items/currency).
 * PDF binaries remain Redis-cached separately via {@see DownloadInvoicePdfHandler}.
 */
final readonly class GetInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryPort $invoices,
    ) {}

    public function handle(string $uuid): InvoiceEloquentModel
    {
        return $this->invoices->findByUuid($uuid)
            ?? throw (new ModelNotFoundException)->setModel(InvoiceEloquentModel::class, [$uuid]);
    }
}
