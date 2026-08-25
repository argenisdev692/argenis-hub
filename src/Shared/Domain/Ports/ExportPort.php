<?php

declare(strict_types=1);

namespace Shared\Domain\Ports;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reusable export contract — written ONCE, consumed by every module.
 *
 * Modules ship only a `{Entity}ExportTransformer` (row → column map) + a thin
 * controller; the writer/streamer/PDF-renderer mechanics live behind this port
 * (BACKEND-PHP §8). A module re-implementing the writer is a DRY FAIL.
 */
interface ExportPort
{
    /**
     * Streamed CSV (`.csv`) or Excel (`.xlsx`) — driver selected by the
     * `$filename` extension. `$rows` SHOULD be a LazyCollection / generator so
     * large datasets stream instead of loading into memory.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<string, scalar|null>>  $rows
     */
    public function tabular(string $filename, array $headers, iterable $rows): StreamedResponse;

    /**
     * PDF rendered from a Blade view.
     *
     * @param  array<string, mixed>  $data
     */
    public function pdf(
        string $filename,
        string $view,
        array $data,
        string $paper = 'a4',
        string $orientation = 'landscape',
    ): Response;
}
