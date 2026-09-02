<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Queries;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Invoices\Application\Support\InvoiceCacheKeys;
use Modules\Invoices\Application\Support\InvoicePdfFilename;
use Modules\Invoices\Domain\Ports\InvoicePdfRendererPort;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;

/**
 * Resolves a single invoice PDF binary. Redis caches the payload keyed by UUID +
 * `updated_at` so edits never re-serve a stale currency/totals snapshot.
 * Mutations still forget the legacy unversioned key for compatibility.
 */
final readonly class DownloadInvoicePdfHandler
{
    public function __construct(
        private GetInvoiceHandler $get,
        private InvoiceRepositoryPort $invoices,
        private InvoicePdfRendererPort $renderer,
        private Cache $cache,
    ) {}

    /**
     * @return array{binary: string, filename: string}
     */
    public function handle(string $uuid): array
    {
        $invoice = $this->get->handle($uuid);
        $version = $invoice->updated_at?->getTimestamp() ?? 0;
        $cacheKey = InvoiceCacheKeys::pdf($uuid, $version);

        $binary = $this->cache->remember(
            $cacheKey,
            now()->addDay(),
            function () use ($uuid): string {
                $fresh = $this->invoices->findByUuid($uuid)
                    ?? throw (new ModelNotFoundException)->setModel(InvoiceEloquentModel::class, [$uuid]);

                return $this->renderer->render($fresh);
            },
        );

        return [
            'binary' => $binary,
            'filename' => InvoicePdfFilename::forInvoice($invoice),
        ];
    }
}
