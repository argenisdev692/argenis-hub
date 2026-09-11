<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Queue;

use Illuminate\Contracts\Cache\Repository as Cache;
use Modules\Invoices\Application\Support\InvoiceCacheKeys;
use Modules\Invoices\Domain\Ports\InvoicePdfCachePort;

/**
 * Redis-backed {@see InvoicePdfCachePort}. Forgets the unversioned PDF key and,
 * on refresh, queues {@see GenerateInvoicePdfJob} after the surrounding
 * transaction commits, so the worker never renders a row that rolled back.
 *
 * Versioned keys (`invoice_pdf_{uuid}_{updated_at}`) need no forgetting: every
 * write moves `updated_at`, so they stop being read and simply expire.
 */
final readonly class QueuedInvoicePdfCache implements InvoicePdfCachePort
{
    public function __construct(private Cache $cache) {}

    public function forget(string ...$uuids): void
    {
        foreach ($uuids as $uuid) {
            $this->cache->forget(InvoiceCacheKeys::pdf($uuid));
        }
    }

    public function refresh(string ...$uuids): void
    {
        $this->forget(...$uuids);

        foreach ($uuids as $uuid) {
            GenerateInvoicePdfJob::dispatch($uuid)->afterCommit();
        }
    }
}
