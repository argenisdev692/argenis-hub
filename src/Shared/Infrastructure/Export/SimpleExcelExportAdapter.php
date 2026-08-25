<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Export;

use Illuminate\Http\Response;
use Shared\Domain\Ports\ExportPort;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bound implementation of {@see ExportPort}.
 *
 * Owns the tabular mechanism (CSV + Excel via spatie/simple-excel — the writer
 * is auto-selected from the filename extension, so CSV needs no extra package)
 * and delegates PDF to {@see DomPdfExportAdapter}, keeping each rendering
 * mechanism in a single-responsibility class while exposing ONE port to modules.
 */
final readonly class SimpleExcelExportAdapter implements ExportPort
{
    public function __construct(private DomPdfExportAdapter $pdf) {}

    public function tabular(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $filename = $this->withTimestamp($filename);
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'csv';

        // Defer all writing into the StreamedResponse callback. SimpleExcel's own
        // `streamDownload()->toBrowser()` opens and closes `php://output` eagerly
        // during the request; that closes the process' stdout under PHPUnit
        // ("Premature end of PHP process"). Writing inside the callback keeps the
        // export memory-safe (LazyCollection/generator) AND test-safe — the closure
        // only runs when the response is sent or `streamedContent()` is called.
        return response()->streamDownload(function () use ($headers, $rows, $extension): void {
            $writer = SimpleExcelWriter::create('php://output', $extension) // driver picked from the type
                ->addHeader($headers);

            foreach ($rows as $row) {
                $writer->addRow($row);
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => $extension === 'csv'
                ? 'text/csv'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function pdf(
        string $filename,
        string $view,
        array $data,
        string $paper = 'a4',
        string $orientation = 'landscape',
    ): Response {
        return $this->pdf->render($this->withTimestamp($filename), $view, $data, $paper, $orientation);
    }

    /**
     * Prefixes the base name with a sortable `Ymd-His` timestamp so repeated
     * exports never collide and stay chronologically ordered on disk
     * (e.g. `permissions.pdf` → `20260710-143022-permissions.pdf`). The
     * extension is preserved so the tabular writer's driver selection is
     * unaffected.
     */
    private function withTimestamp(string $filename): string
    {
        return now()->format('Ymd-His').'-'.$filename;
    }
}
