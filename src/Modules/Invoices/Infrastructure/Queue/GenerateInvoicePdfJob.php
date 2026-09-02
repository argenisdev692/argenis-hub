<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Invoices\Application\Support\InvoiceCacheKeys;
use Modules\Invoices\Domain\Ports\InvoicePdfRendererPort;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Throwable;

/**
 * Pre-warms the Redis-cached invoice PDF after create/update so the next
 * download hits cache. Horizon (Redis queue) owns the worker.
 */
#[Queue('default')]
#[Tries(3)]
#[Timeout(120)]
final class GenerateInvoicePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly string $invoiceUuid) {}

    public function handle(
        InvoiceRepositoryPort $invoices,
        InvoicePdfRendererPort $renderer,
        Cache $cache,
    ): void {
        $invoice = $invoices->findByUuid($this->invoiceUuid);

        if ($invoice === null) {
            Log::warning('invoices.pdf.invoice_missing', ['uuid' => $this->invoiceUuid]);

            return;
        }

        try {
            $binary = $renderer->render($invoice);
            $version = $invoice->updated_at?->getTimestamp() ?? 0;
            $cache->put(InvoiceCacheKeys::pdf($this->invoiceUuid, $version), $binary, now()->addDay());
            // Legacy unversioned key (pre-versioned cache) — drop so downloads cannot hit it.
            $cache->forget(InvoiceCacheKeys::pdf($this->invoiceUuid));
        } catch (Throwable $exception) {
            Log::warning('invoices.pdf.generation_failed', [
                'uuid' => $this->invoiceUuid,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['invoices', 'invoice-pdf:'.$this->invoiceUuid];
    }
}
