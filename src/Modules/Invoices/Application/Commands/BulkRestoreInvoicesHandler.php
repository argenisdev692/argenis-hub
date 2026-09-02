<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;
use Modules\Invoices\Application\Support\InvoiceCacheKeys;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Modules\Invoices\Infrastructure\Queue\GenerateInvoicePdfJob;
use Shared\Application\DTOs\BulkUuidsData;

final readonly class BulkRestoreInvoicesHandler
{
    public function __construct(
        private InvoiceRepositoryPort $invoices,
        private Cache $cache,
    ) {}

    public function handle(BulkUuidsData $data): int
    {
        $count = DB::transaction(fn () => $this->invoices->bulkRestoreByUuid($data->uuids));

        foreach ($data->uuids as $uuid) {
            $this->cache->forget(InvoiceCacheKeys::invoice($uuid));
            $this->cache->forget(InvoiceCacheKeys::pdf($uuid));
            GenerateInvoicePdfJob::dispatch($uuid)->afterCommit();
        }

        return $count;
    }
}
