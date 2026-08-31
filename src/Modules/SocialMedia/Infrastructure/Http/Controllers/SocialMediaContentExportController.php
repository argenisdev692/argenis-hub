<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Post\Infrastructure\Http\Controllers\PostExportController;
use Modules\SocialMedia\Application\DTOs\SocialMediaContentFilterData;
use Modules\SocialMedia\Infrastructure\Http\Export\SocialMediaContentExportTransformer;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the filtered content list as CSV / Excel / PDF. Thin: reuses the
 * SAME {@see SocialMediaContentFilterData} + `scopeApplyFilters()` as the list
 * query (DRY) and the Shared {@see ExportPort} — mirrors
 * {@see PostExportController}.
 */
final readonly class SocialMediaContentExportController
{
    public function __construct(private ExportPort $export) {}

    public function __invoke(Request $request): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $filters = SocialMediaContentFilterData::validateAndCreate($request);

        $rows = SocialMediaContentEloquentModel::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->orderByDesc('created_at')
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'social-media.pdf',
                'exports.pdf.social-media',
                [
                    'rows' => $rows->map(SocialMediaContentExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "social-media.{$format}",
                ['Topic', 'Funnel Stage', 'Content Status', 'Overall Score', 'Scores Passed', 'Created', 'Status'],
                $rows->map(SocialMediaContentExportTransformer::transformForTable(...)),
            ),
        };
    }
}
