<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Export;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Shared\Domain\Ports\ExportPort;

/**
 * PDF rendering mechanism (barryvdh/laravel-dompdf) isolated as a single-purpose
 * collaborator. Holds NO tabular logic — SRP. Consumed by the bound ExportPort
 * adapter to satisfy {@see ExportPort::pdf()}.
 *
 * The dataset passed in `$data` SHOULD be a `cursor()`/lazy collection so the
 * Blade view iterates without materialising the whole result set.
 */
final readonly class DomPdfExportAdapter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(
        string $filename,
        string $view,
        array $data,
        string $paper = 'a4',
        string $orientation = 'landscape',
    ): Response {
        return Pdf::loadView($view, $data)
            ->setPaper($paper, $orientation)
            ->download($filename);
    }
}
