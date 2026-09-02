<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Invoices\Application\DTOs\InvoiceFilterData;
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
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
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
