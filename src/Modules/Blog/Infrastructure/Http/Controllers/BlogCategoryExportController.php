<?php

declare(strict_types=1);

namespace Modules\Blog\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Blog\Application\DTOs\BlogCategoryFilterData;
use Modules\Blog\Infrastructure\Http\Export\BlogCategoryExportTransformer;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the filtered blog-category list as CSV / Excel / PDF. Thin: reuses the
 * SAME {@see BlogCategoryFilterData} + `scopeApplyFilters()` as the list query
 * (DRY) and the Shared {@see ExportPort} mechanism — this module ships only the
 * transformer. The `suspended` status maps to `onlyTrashed()`, mirroring the
 * repository's `paginate()`.
 */
final readonly class BlogCategoryExportController
{
    public function __construct(private ExportPort $export) {}

    public function __invoke(Request $request): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $filters = BlogCategoryFilterData::validateAndCreate($request);

        $rows = BlogCategoryEloquentModel::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->with('user:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'blog-categories.pdf',
                'exports.pdf.blog-categories',
                [
                    'rows' => $rows->map(BlogCategoryExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "blog-categories.{$format}",
                ['Name', 'Description', 'Author', 'Created', 'Status'],
                $rows->map(BlogCategoryExportTransformer::transformForTable(...)),
            ),
        };
    }
}
