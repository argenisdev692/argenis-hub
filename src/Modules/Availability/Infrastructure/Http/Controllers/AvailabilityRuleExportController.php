<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Availability\Application\DTOs\AvailabilityRuleFilterData;
use Modules\Availability\Infrastructure\Http\Export\AvailabilityRuleExportTransformer;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityRuleEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the filtered weekly-rule list as CSV / Excel / PDF. Thin: reuses the
 * SAME {@see AvailabilityRuleFilterData} + `scopeApplyFilters()` as the list
 * query (DRY) and the Shared {@see ExportPort} mechanism — this module ships only
 * the transformer. `suspended` status maps to `onlyTrashed()`, mirroring the
 * repository's `paginate()`.
 */
final readonly class AvailabilityRuleExportController
{
    public function __construct(private ExportPort $export) {}

    public function __invoke(Request $request): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $filters = AvailabilityRuleFilterData::validateAndCreate($request);

        $rows = AvailabilityRuleEloquentModel::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'availability-rules.pdf',
                'exports.pdf.availability-rules',
                [
                    'rows' => $rows->map(AvailabilityRuleExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "availability-rules.{$format}",
                ['Day', 'Start', 'End', 'Availability', 'Status'],
                $rows->map(AvailabilityRuleExportTransformer::transformForTable(...)),
            ),
        };
    }
}
