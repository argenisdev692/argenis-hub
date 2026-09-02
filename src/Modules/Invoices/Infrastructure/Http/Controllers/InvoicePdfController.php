<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Invoices\Application\Queries\DownloadInvoicePdfHandler;

/**
 * Downloads a single invoice PDF (portrait A4). Binary is Redis-cached;
 * Horizon warms the cache after create/update via GenerateInvoicePdfJob.
 */
final readonly class InvoicePdfController
{
    public function __invoke(string $uuid, DownloadInvoicePdfHandler $download): Response
    {
        $pdf = $download->handle($uuid);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $pdf['filename']) ?: 'invoice.pdf';

        return response($pdf['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
