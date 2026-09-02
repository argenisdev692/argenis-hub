<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Availability\Application\DTOs\AvailabilityExceptionFilterData;
use Modules\Availability\Infrastructure\Http\Export\AvailabilityExceptionExportTransformer;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityExceptionEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the filtered date-exception list as CSV / Excel / PDF. Thin: reuses the
 * SAME {@see AvailabilityExceptionFilterData} + `scopeApplyFilters()` as the list
 * query (DRY) and the Shared {@see ExportPort} mechanism — this module ships only
 * the transformer. `suspended` status maps to `onlyTrashed()`, mirroring the
 * repository's `paginate()`.
 */
final readonly class AvailabilityExceptionExportController
{
    public function __construct(private ExportPort $export) {}

    public function __invoke(Request $request): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $filters = AvailabilityExceptionFilterData::validateAndCreate($request);

        $rows = AvailabilityExceptionEloquentModel::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->orderBy('date')
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'availability-exceptions.pdf',
                'exports.pdf.availability-exceptions',
                [
                    'rows' => $rows->map(AvailabilityExceptionExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "availability-exceptions.{$format}",
                ['Date', 'Availability', 'Start', 'End', 'Reason', 'Source', 'Status'],
                $rows->map(AvailabilityExceptionExportTransformer::transformForTable(...)),
            ),
        };
    }
}
