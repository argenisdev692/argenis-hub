<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Campaigns\Application\DTOs\CampaignFilterData;
use Modules\Campaigns\Infrastructure\Http\Export\CampaignExportTransformer;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;
use Modules\SocialMedia\Infrastructure\Http\Controllers\SocialMediaContentExportController;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the filtered campaign list as CSV / Excel / PDF. Thin: reuses the
 * SAME {@see CampaignFilterData} + `scopeApplyFilters()` as the list query
 * (DRY) and the Shared {@see ExportPort} — mirrors
 * {@see SocialMediaContentExportController}.
 */
final readonly class CampaignExportController
{
    public function __construct(private ExportPort $export) {}

    public function __invoke(Request $request): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $filters = CampaignFilterData::validateAndCreate($request);

        $rows = CampaignEloquentModel::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->orderByDesc('created_at')
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'campaigns.pdf',
                'exports.pdf.campaigns',
                [
                    'rows' => $rows->map(CampaignExportTransformer::transformForPdf(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "campaigns.{$format}",
                ['Topic', 'Funnel Stage', 'Platform', 'Campaign Status', 'Success Probability', 'Scores Passed', 'Created', 'Status'],
                $rows->map(CampaignExportTransformer::transformForTable(...)),
            ),
        };
    }
}
