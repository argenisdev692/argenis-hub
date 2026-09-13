<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\VideoEdits\Application\Queries\GetVideoEditReportHandler;
use Shared\Domain\Ports\ExportPort;

/**
 * The AI decision report, on screen and as a PDF (US-14).
 *
 * Both formats come from the same stored data through one handler, so the PDF
 * can never disagree with the page — and neither reprocesses the video (EX-8).
 * Owner scoping lives in the handler: another user's report is a 404, not a 403.
 */
final readonly class VideoEditReportController
{
    public function __construct(
        private GetVideoEditReportHandler $report,
        private ExportPort $export,
    ) {}

    #[QueryParameter('format', description: '`json` for the report data, `pdf` to download it.', type: 'string', default: 'json', example: 'pdf')]
    public function __invoke(Request $request, string $uuid): JsonResponse|Response
    {
        $report = $this->report->handle($uuid, (int) $request->user()->id);

        return match ((string) $request->string('format', 'json')) {
            'pdf' => $this->export->pdf(
                "video-edit-report-{$uuid}.pdf",
                'exports.pdf.video-edit-report',
                [...$report->toArray(), 'generatedAt' => now()->format('F j, Y H:i')],
                orientation: 'portrait',
            ),
            default => response()->json($report),
        };
    }
}
