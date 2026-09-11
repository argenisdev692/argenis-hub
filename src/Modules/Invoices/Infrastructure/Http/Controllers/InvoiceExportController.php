<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Invoices\Application\DTOs\InvoiceFilterData;
use Modules\Invoices\Application\Support\InvoiceFilterSummary;
use Modules\Invoices\Infrastructure\Http\Export\InvoiceExportTransformer;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams filtered invoices as CSV / XLSX / PDF. Reuses InvoiceFilterData +
 * scopeApplyFilters (DRY) and Shared ExportPort.
 */
final readonly class InvoiceExportController
{
    public function __construct(private ExportPort $export) {}

    public function __invoke(Request $request): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $filters = InvoiceFilterData::validateAndCreate($request);

        $rows = InvoiceEloquentModel::query()
            ->applyFilters($filters)
            ->with('client:id,client_name')
            ->orderByDesc('issue_date')
            ->orderByDesc('sequence')
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'invoices.pdf',
                'exports.pdf.invoices',
                [
                    'rows' => $rows->map(InvoiceExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                    // A printed report is read away from the screen that
                    // produced it, so it has to say which invoices it holds.
                    'filterSummary' => InvoiceFilterSummary::describe($filters),
                ],
            ),
            default => $this->export->tabular(
                "invoices.{$format}",
                ['Number', 'Client', 'Issue date', 'Due date', 'Total', 'Paid', 'Status'],
                $rows->map(InvoiceExportTransformer::transformForExcel(...)),
            ),
        };
    }
}
