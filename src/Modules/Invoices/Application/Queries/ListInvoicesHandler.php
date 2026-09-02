<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Invoices\Application\DTOs\InvoiceFilterData;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;

final readonly class ListInvoicesHandler
{
    public function __construct(private InvoiceRepositoryPort $invoices) {}

    public function handle(InvoiceFilterData $filters, int $perPage): LengthAwarePaginator
    {
        return $this->invoices->paginate($filters, $perPage);
    }
}
