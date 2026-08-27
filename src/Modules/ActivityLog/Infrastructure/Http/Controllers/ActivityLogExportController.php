<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\ActivityLog\Application\DTOs\ActivityLogFilterData;
use Modules\ActivityLog\Infrastructure\Http\Export\ActivityLogExportTransformer;
use Shared\Domain\Ports\ExportPort;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the filtered activity-log trail as CSV / Excel / PDF. Thin: reuses the
 * SAME {@see ActivityLogFilterData::applyTo()} as the list query (DRY) and the
 * Shared {@see ExportPort} mechanism — this module ships only the transformer.
 */
final readonly class ActivityLogExportController
{
    public function __construct(private ExportPort $export) {}

    public function __invoke(Request $request, ActivityLogFilterData $filters): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $rows = $filters
            ->applyTo(Activity::query()->with('causer'))
            ->orderBy('created_at', $filters->normalizedSortDirection())
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'activity-logs.pdf',
                'exports.pdf.activity-logs',
                [
                    'rows' => $rows->map(ActivityLogExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "activity-logs.{$format}",
                ['When', 'Actor', 'Event', 'Description', 'Subject', 'Log'],
                $rows->map(ActivityLogExportTransformer::transformForTable(...)),
            ),
        };
    }
}
