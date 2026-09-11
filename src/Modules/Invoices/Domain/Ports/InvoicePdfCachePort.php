<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Ports;

/**
 * Keeps the cached invoice PDF binaries in step with the invoices they render.
 * Command handlers call it after a write; the adapter owns the cache store and
 * the queue, so Application touches neither.
 */
interface InvoicePdfCachePort
{
    /**
     * Drops the cached PDF of each invoice — for writes after which the
     * document must not be re-rendered (soft delete).
     */
    public function forget(string ...$uuids): void;

    /**
     * Drops the cached PDF of each invoice and queues a fresh render, so the
     * next download is warm (create, update, restore).
     */
    public function refresh(string ...$uuids): void;
}
