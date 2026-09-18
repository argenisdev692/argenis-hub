<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Modules\CvJobStudio\Application\DTOs\StudioPostingFilterData;
use Modules\CvJobStudio\Infrastructure\Http\Export\StudioPostingExportTransformer;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the authenticated user's filtered postings as CSV / XLSX / PDF.
 * Reuses `StudioPostingFilterData` + `scopeApplyFilters` so list and export
 * can never drift. `ownedBy()` is the security boundary — a missing owner
 * scope here would leak every candidate's postings in one request.
 */
final readonly class StudioPostingExportController
{
    /** @var list<string> */
    private const array HEADERS = ['Title', 'Employer', 'Location', 'Remote scope', 'Status', 'Score', 'Band', 'Cap', 'Channel', 'Created'];

    /** @var list<string> */
    private const array FORMATS = ['csv', 'xlsx', 'pdf'];

    /** @var list<string> */
    private const array EXPORT_COLUMNS = [
        'id',
        'uuid',
        'user_id',
        'profile_id',
        'employer_name',
        'title',
        'location_text',
        'remote_scope',
        'status',
        'discovery_channel',
        'created_at',
        'deleted_at',
    ];

    public function __construct(private ExportPort $export) {}

    #[QueryParameter('format', description: 'Export file format: `csv`, `xlsx` or `pdf`.', type: 'string', default: 'csv', example: 'xlsx')]
    public function __invoke(Request $request, StudioPostingFilterData $filters): StreamedResponse|Response
    {
        /** @var array{format?: string} $validated */
        $validated = $request->validate(['format' => ['sometimes', 'string', Rule::in(self::FORMATS)]]);
        $format = $validated['format'] ?? 'csv';

        $rows = StudioPostingEloquentModel::query()
            ->ownedBy((int) $request->user()->id)
            ->applyFilters($filters)
            ->with('scores')
            ->select(self::EXPORT_COLUMNS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'studio-postings.pdf',
                'exports.pdf.cv_job_studio',
                [
                    'rows' => $rows->map(StudioPostingExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "studio-postings.{$format}",
                self::HEADERS,
                $rows->map(StudioPostingExportTransformer::transformForExcel(...)),
            ),
        };
    }
}
