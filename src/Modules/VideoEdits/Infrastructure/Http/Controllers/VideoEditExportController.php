<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\VideoEdits\Application\DTOs\VideoEditFilterData;
use Modules\VideoEdits\Application\Support\VideoEditFilterSummary;
use Modules\VideoEdits\Infrastructure\Http\Export\VideoEditExportTransformer;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the caller's edit history as CSV / XLSX / PDF.
 *
 * Reuses `VideoEditFilterData` + `scopeApplyFilters` (DRY — BACKEND-PHP §5.2)
 * and the Shared `ExportPort`, so the module ships only a column map and this
 * thin controller.
 *
 * **`ownedBy()` is the security boundary** (FR-21): an export is the one
 * endpoint that returns many rows at once, so a missing owner scope here leaks
 * every user's history in a single request rather than one record at a time.
 * `VideoEditExportTest` asserts it directly.
 */
final readonly class VideoEditExportController
{
    /** @var list<string> */
    private const array HEADERS = [
        'Reference', 'Mode', 'Status', 'Clips', 'Original', 'Final', 'Removed', 'Cuts', 'Created', 'Completed',
    ];

    /** @var list<string> */
    private const array FORMATS = ['csv', 'xlsx', 'pdf'];

    /**
     * Only what the report prints — the row is never hydrated with result paths
     * or failure internals that must not leave the backend (FR-20).
     *
     * @var list<string>
     */
    private const array EXPORT_COLUMNS = [
        'id', 'uuid', 'mode', 'status', 'original_duration_ms', 'final_duration_ms',
        'removed_duration_ms', 'applied_cut_count', 'created_at', 'completed_at',
    ];

    public function __construct(private ExportPort $export) {}

    #[QueryParameter('format', description: 'Export file format: `csv`, `xlsx` or `pdf`.', type: 'string', default: 'csv', example: 'xlsx')]
    public function __invoke(Request $request, VideoEditFilterData $filters): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, self::FORMATS, true), 422);

        $rows = VideoEditEloquentModel::query()
            ->select(self::EXPORT_COLUMNS)
            // withCount, not a `sources` eager load: the report prints the
            // number of clips, and loading the rows to count them would be the
            // N+1 this project bans (BACKEND-PHP §4.1).
            ->withCount('sources')
            ->ownedBy((int) $request->user()->id)
            ->applyFilters($filters)
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'video-edits.pdf',
                'exports.pdf.video-edits',
                [
                    'rows' => $rows->map(VideoEditExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                    'filterSummary' => VideoEditFilterSummary::describe($filters),
                ],
            ),
            default => $this->export->tabular(
                "video-edits.{$format}",
                self::HEADERS,
                $rows->map(VideoEditExportTransformer::transformForExcel(...)),
            ),
        };
    }
}
