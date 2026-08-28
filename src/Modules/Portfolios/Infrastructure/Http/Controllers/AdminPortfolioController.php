<?php

declare(strict_types=1);

namespace Modules\Portfolios\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Portfolios\Application\Commands\BulkDeletePortfolioHandler;
use Modules\Portfolios\Application\Commands\BulkRestorePortfolioHandler;
use Modules\Portfolios\Application\Commands\CreatePortfolioHandler;
use Modules\Portfolios\Application\Commands\DeletePortfolioHandler;
use Modules\Portfolios\Application\Commands\RestorePortfolioHandler;
use Modules\Portfolios\Application\Commands\UpdatePortfolioHandler;
use Modules\Portfolios\Application\DTOs\PortfolioFilterData;
use Modules\Portfolios\Application\Queries\GetPortfolioHandler;
use Modules\Portfolios\Application\Queries\ListPortfoliosHandler;
use Modules\Portfolios\Infrastructure\Http\Export\PortfolioExportTransformer;
use Modules\Portfolios\Infrastructure\Http\Requests\BulkDeletePortfolioRequest;
use Modules\Portfolios\Infrastructure\Http\Requests\BulkRestorePortfolioRequest;
use Modules\Portfolios\Infrastructure\Http\Requests\StorePortfolioRequest;
use Modules\Portfolios\Infrastructure\Http\Requests\UpdatePortfolioRequest;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin showcase surface: one Inertia page (`page()`) plus the JSON data
 * endpoints it consumes under `/data/admin/portfolios` (Controller Fusion Rule
 * — the two flows share one entity and one permission set). Each method is
 * guarded by its own permission at the route level (`web.php`), not re-checked
 * here.
 */
final readonly class AdminPortfolioController
{
    public function __construct(
        private ListPortfoliosHandler $listPortfolios,
        private GetPortfolioHandler $getPortfolio,
        private CreatePortfolioHandler $createPortfolio,
        private UpdatePortfolioHandler $updatePortfolio,
        private DeletePortfolioHandler $deletePortfolio,
        private RestorePortfolioHandler $restorePortfolio,
        private BulkDeletePortfolioHandler $bulkDeletePortfolio,
        private BulkRestorePortfolioHandler $bulkRestorePortfolio,
        private ExportPort $export,
    ) {}

    /**
     * The table fetches its own rows via Pinia Colada against `index()`, so the
     * page itself carries no server-rendered props.
     */
    public function page(): InertiaResponse
    {
        return Inertia::render('portfolios/Index');
    }

    public function index(PortfolioFilterData $filters): JsonResponse
    {
        return response()->json($this->listPortfolios->handle($filters));
    }

    /**
     * Streams the filtered showcase as CSV / Excel / PDF. Reuses the SAME
     * `PortfolioEloquentModel::applyFilters()` the list query runs (DRY) minus
     * pagination, so an export is exactly what the table currently shows. The
     * date-range invariant (`date_from` ≤ `date_to`) is enforced by
     * {@see PortfolioFilterData}; an unknown `format` is a 422.
     */
    public function export(Request $request, PortfolioFilterData $filters): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $rows = PortfolioEloquentModel::query()
            ->applyFilters($filters)
            ->with('user:id,first_name,last_name')
            ->select([
                'id', 'uuid', 'user_id', 'title', 'client_name', 'project_type',
                'tech_stack', 'live_url', 'is_public', 'published_at', 'created_at', 'deleted_at',
            ])
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'portfolios.pdf',
                'exports.pdf.portfolios',
                [
                    'rows' => $rows->map(PortfolioExportTransformer::toRow(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "portfolios.{$format}",
                PortfolioExportTransformer::headers(),
                $rows->map(PortfolioExportTransformer::toRow(...)),
            ),
        };
    }

    public function store(StorePortfolioRequest $request): JsonResponse
    {
        $portfolio = $this->createPortfolio->handle(
            $request->validated(),
            (int) $request->user()?->getAuthIdentifier(),
        );

        return response()->json($portfolio, 201);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json($this->getPortfolio->handle($uuid));
    }

    public function update(string $uuid, UpdatePortfolioRequest $request): JsonResponse
    {
        return response()->json($this->updatePortfolio->handle($uuid, $request->validated()));
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->deletePortfolio->handle($uuid);

        return response()->json(status: 204);
    }

    public function restore(string $uuid): JsonResponse
    {
        return response()->json($this->restorePortfolio->handle($uuid));
    }

    public function bulkDelete(BulkDeletePortfolioRequest $request): JsonResponse
    {
        $deleted = $this->bulkDeletePortfolio->handle($request->validated('uuids'));

        return response()->json(['deleted' => $deleted]);
    }

    public function bulkRestore(BulkRestorePortfolioRequest $request): JsonResponse
    {
        $restored = $this->bulkRestorePortfolio->handle($request->validated('uuids'));

        return response()->json(['restored' => $restored]);
    }
}
