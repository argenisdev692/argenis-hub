<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Campaigns\Application\Queries\GetCampaignHandler;
use Modules\Campaigns\Infrastructure\Queue\GenerateCampaignJob;
use Shared\Domain\Ports\ExportPort;

/**
 * On-demand single-campaign PDF scorecard — headline copy, the 5 quality
 * scores with their factors/explanations, success probability, lead-form
 * questions and targeting suggestions. Rendered fresh on every request
 * (dompdf is cheap CPU-only work, no AI cost) rather than generated once by
 * {@see GenerateCampaignJob} and
 * stored on R2 — so an edit made on the review screen is always reflected
 * the next time this is downloaded. Distinct from
 * {@see CampaignExportController}, which exports the filtered LIST as a
 * table; this exports ONE campaign as a detailed report.
 */
final readonly class CampaignReportController
{
    public function __construct(private ExportPort $export) {}

    public function __invoke(string $uuid, GetCampaignHandler $get): Response
    {
        $campaign = $get->handle($uuid);

        return $this->export->pdf(
            "campaign-{$campaign->uuid}-report.pdf",
            'exports.pdf.campaign-report',
            [
                'campaign' => $campaign,
                'generatedAt' => now()->format('F j, Y H:i'),
            ],
            'a4',
            'portrait',
        );
    }
}
