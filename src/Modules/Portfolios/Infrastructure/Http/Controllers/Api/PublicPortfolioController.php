<?php

declare(strict_types=1);

namespace Modules\Portfolios\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Portfolios\Application\DTOs\PublicPortfolioData;
use Modules\Portfolios\Application\Queries\ListPublicPortfoliosHandler;
use Modules\Portfolios\Infrastructure\Http\Export\PublicPortfolioExportTransformer;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public portfolio showcase for the landing page and the standalone Astro
 * sites — same reasoning as the Services module's public catalog controller:
 *
 * - a Spatie Data allowlist ({@see PublicPortfolioData}) so the owner id and
 *   the soft-delete timestamp physically cannot appear;
 * - rate limiters (list + the heavier export separately), so an unauthenticated
 *   endpoint cannot become a cheap amplifier (OWASP §14);
 * - a 30-minute cache in the query handler, so showcase browsing does not
 *   become database traffic for a table that changes a few times a month.
 *
 * Only rows that are `is_public` AND already `published_at` are ever visible
 * here — enforced once, in `PortfolioEloquentModel::scopePublished()`.
 */
final readonly class PublicPortfolioController
{
    public function __construct(
        private ListPublicPortfoliosHandler $listPublicPortfolios,
        private ExportPort $export,
    ) {}

    /**
     * List the published portfolio projects, in display order.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->listPublicPortfolios->handle());
    }

    /**
     * Download the same published showcase as CSV / Excel / PDF. Narrower
     * columns than the admin export ({@see PublicPortfolioExportTransformer}) —
     * no owner, no lifecycle status. An unknown `format` is a 422.
     */
    public function export(Request $request): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $rows = PortfolioEloquentModel::query()
            ->published()
            ->orderBy('sort_order')
            ->select([
                'id', 'uuid', 'title', 'client_name', 'project_type',
                'tech_stack', 'live_url', 'published_at',
            ])
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'portfolios.pdf',
                'exports.pdf.portfolios-public',
                [
                    'rows' => $rows->map(PublicPortfolioExportTransformer::toRow(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "portfolios.{$format}",
                PublicPortfolioExportTransformer::headers(),
                $rows->map(PublicPortfolioExportTransformer::toRow(...)),
            ),
        };
    }
}
