<?php

declare(strict_types=1);

namespace Modules\Cvs\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Modules\Cvs\Application\DTOs\CvFilterData;
use Modules\Cvs\Infrastructure\Http\Export\CvExportTransformer;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the authenticated user's filtered CVs as CSV / XLSX / PDF. Reuses
 * `CvFilterData` + `scopeApplyFilters` so list and export can never drift.
 *
 * **`ownedBy()` is the security boundary**: an export returns many rows at
 * once, so a missing owner scope here would leak every user's CVs in a single
 * request. `CvOwnershipTest` asserts it directly.
 */
final readonly class CvExportController
{
    /** @var list<string> */
    private const array HEADERS = ['Title', 'Niche', 'Primary', 'Type', 'Filename', 'Owner', 'Created', 'Status'];

    /** @var list<string> */
    private const array FORMATS = ['csv', 'xlsx', 'pdf'];

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

    #[QueryParameter('format', description: 'Export file format: `csv`, `xlsx` or `pdf`.', type: 'string', default: 'csv', example: 'xlsx')]
    public function __invoke(Request $request, CvFilterData $filters): StreamedResponse|Response
    {
        /** @var array{format?: string} $validated */
        $validated = $request->validate(['format' => ['sometimes', 'string', Rule::in(self::FORMATS)]]);
        $format = $validated['format'] ?? 'csv';

        $rows = CvEloquentModel::query()
            ->ownedBy((int) $request->user()->id)
            ->applyFilters($filters)
            ->with('user:id,first_name,last_name')
            ->select(self::EXPORT_COLUMNS)
            ->orderByDesc('created_at')
            // Unique tie-breaker: `lazy()` pages with LIMIT/OFFSET, and rows
            // sharing a `created_at` could otherwise repeat or vanish between chunks.
            ->orderByDesc('id')
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
                self::HEADERS,
                $rows->map(CvExportTransformer::transformForExcel(...)),
            ),
        };
    }
}
