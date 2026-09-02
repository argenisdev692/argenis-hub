<?php

declare(strict_types=1);

namespace Modules\Cvs\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Cvs\Application\DTOs\CvFilterData;
use Modules\Cvs\Infrastructure\Http\Export\CvExportTransformer;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the authenticated user's filtered CVs as CSV / XLSX / PDF. Reuses
 * `CvFilterData` + `scopeApplyFilters` so list and export can never drift.
 */
final readonly class CvExportController
{
    /**
     * `raw_text` and `file_path` are excluded: the first is a longText PII
     * column that would balloon every streamed row, the second is a private R2
     * key. Neither appears in any export column.
     *
     * @var list<string>
     */
    private const array EXPORT_COLUMNS = [
        'id',
        'uuid',
        'user_id',
        'title',
        'niche',
        'is_primary',
        'file_type',
        'original_filename',
        'created_at',
        'deleted_at',
    ];

    public function __construct(private ExportPort $export) {}

    public function __invoke(Request $request): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $filters = CvFilterData::validateAndCreate($request);

        $rows = CvEloquentModel::query()
            ->ownedBy((int) $request->user()->id)
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->with('user:id,first_name,last_name')
            ->select(self::EXPORT_COLUMNS)
            ->orderByDesc('created_at')
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'cvs.pdf',
                'exports.pdf.cvs',
                [
                    'rows' => $rows->map(CvExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "cvs.{$format}",
                ['Title', 'Niche', 'Primary', 'Type', 'Filename', 'Owner', 'Created', 'Status'],
                $rows->map(CvExportTransformer::transformForTable(...)),
            ),
        };
    }
}
