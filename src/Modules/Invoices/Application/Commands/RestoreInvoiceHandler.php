<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;
use Modules\Invoices\Application\Support\InvoiceCacheKeys;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Modules\Invoices\Infrastructure\Queue\GenerateInvoicePdfJob;

final readonly class RestoreInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryPort $invoices,
        private Cache $cache,
    ) {}

    public function handle(string $uuid): void
    {
        DB::transaction(fn () => $this->invoices->restore($uuid));

        $this->cache->forget(InvoiceCacheKeys::invoice($uuid));
        $this->cache->forget(InvoiceCacheKeys::pdf($uuid));
        GenerateInvoicePdfJob::dispatch($uuid)->afterCommit();
    }
}
