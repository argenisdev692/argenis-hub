<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Http\Controllers;

use App\Models\User;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Modules\CourseScripts\Application\DTOs\CourseFilterData;
use Modules\CourseScripts\Domain\Enums\VideoScriptStatus;
use Modules\CourseScripts\Infrastructure\Http\Export\CourseExportTransformer;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the author's course list as CSV / XLSX / PDF (US-16).
 *
 * Reuses `CourseFilterData` + `scopeApplyFilters` (DRY — BACKEND-PHP §5.2) and
 * the Shared `ExportPort`. **`ownedBy()` is the security boundary** (FR-53):
 * an export returns many rows at once, so a missing owner scope would leak
 * every author's courses in one request.
 */
final readonly class CourseExportController
{
    /** @var list<string> */
    private const array HEADERS = [
        'Reference', 'Title', 'Language', 'Generation Status', 'Videos', 'Generated', 'Status', 'Created', 'Updated',
    ];

    /** @var list<string> */
    private const array FORMATS = ['csv', 'xlsx', 'pdf'];

    /**
     * Only what the report prints — never notes, the bible or file contents.
     *
     * @var list<string>
     */
    private const array EXPORT_COLUMNS = [
        'id', 'uuid', 'user_id', 'title', 'language', 'status', 'created_at', 'updated_at', 'deleted_at',
    ];

    public function __construct(private ExportPort $export) {}

    #[QueryParameter('format', description: 'Export file format: `csv`, `xlsx` or `pdf`.', type: 'string', default: 'csv', example: 'xlsx')]
    public function __invoke(Request $request, CourseFilterData $filters): StreamedResponse|Response
    {
        /** @var array{format?: string} $validated */
        $validated = $request->validate(['format' => ['sometimes', 'string', Rule::in(self::FORMATS)]]);
        $format = $validated['format'] ?? 'csv';

        /** @var User $user */
        $user = $request->user();

        $rows = CourseEloquentModel::query()
            ->select(self::EXPORT_COLUMNS)
            ->ownedBy($user->id)
            ->applyFilters($filters)
            ->withCount([
                'videos',
                'videos as generated_videos_count' => static fn (Builder $query): Builder => $query->where('script_status', VideoScriptStatus::Generated->value),
            ])
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf('courses.pdf', 'exports.pdf.courses', [
                'rows' => $rows->map(CourseExportTransformer::transformForPdf(...)),
                'generatedAt' => now()->format('F j, Y H:i'),
            ]),
            default => $this->export->tabular("courses.{$format}", self::HEADERS, $rows->map(CourseExportTransformer::transformForExcel(...))),
        };
    }
}
