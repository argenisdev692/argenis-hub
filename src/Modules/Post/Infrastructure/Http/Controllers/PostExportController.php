<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Post\Application\DTOs\PostFilterData;
use Modules\Post\Infrastructure\Http\Export\PostExportTransformer;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the filtered post list as CSV / Excel / PDF. Thin: reuses the SAME
 * {@see PostFilterData} + `scopeApplyFilters()` as the list query (DRY) and the
 * Shared {@see ExportPort} mechanism — this module ships only the transformer.
 */
final readonly class PostExportController
{
    public function __construct(private ExportPort $export) {}

    public function __invoke(Request $request): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $filters = PostFilterData::validateAndCreate($request);

        $rows = PostEloquentModel::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->with(['category:id,uuid,blog_category_name', 'user:id,first_name,last_name'])
            ->orderBy($filters->resolvedSortField(), $filters->resolvedSortDirection())
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'posts.pdf',
                'exports.pdf.posts',
                [
                    'rows' => $rows->map(PostExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "posts.{$format}",
                ['Title', 'Category', 'Author', 'Publication Status', 'SEO Score', 'Created', 'Status'],
                $rows->map(PostExportTransformer::transformForTable(...)),
            ),
        };
    }
}
