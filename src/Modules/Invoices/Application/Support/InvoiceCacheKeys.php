<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Support;

/**
 * Redis cache key helpers for invoice aggregate + generated PDF binaries.
 */
final readonly class InvoiceCacheKeys
{
    public static function invoice(string $uuid): string
    {
        return "invoice_{$uuid}";
    }

    /**
     * PDF binary cache key. Pass `$version` (usually `updated_at` unix ts) so an
     * edit never re-serves a pre-edit PDF after Redis forget races.
     */
    public static function pdf(string $uuid, ?int $version = null): string
    {
        if ($version === null) {
            return "invoice_pdf_{$uuid}";
        }

        return "invoice_pdf_{$uuid}_{$version}";
    }
}
