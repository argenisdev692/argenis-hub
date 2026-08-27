<?php

declare(strict_types=1);

namespace Modules\Services\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Services\Application\Commands\BulkDeleteServiceHandler;
use Modules\Services\Application\Commands\BulkRestoreServiceHandler;
use Modules\Services\Application\Commands\CreateServiceHandler;
use Modules\Services\Application\Commands\DeleteServiceHandler;
use Modules\Services\Application\Commands\RestoreServiceHandler;
use Modules\Services\Application\Commands\UpdateServiceHandler;
use Modules\Services\Application\DTOs\ServiceFilterData;
use Modules\Services\Application\Queries\GetServiceHandler;
use Modules\Services\Application\Queries\ListServicesHandler;
use Modules\Services\Infrastructure\Http\Export\ServiceExportTransformer;
use Modules\Services\Infrastructure\Http\Requests\BulkDeleteServiceRequest;
use Modules\Services\Infrastructure\Http\Requests\BulkRestoreServiceRequest;
use Modules\Services\Infrastructure\Http\Requests\StoreServiceRequest;
use Modules\Services\Infrastructure\Http\Requests\UpdateServiceRequest;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin catalog: one Inertia page (`page()`) plus the JSON data endpoints
 * it consumes under `/data/admin/services` (Controller Fusion Rule — the two
 * flows share one entity and one permission set, so a second controller would
 * only add an import to keep in sync). Each method is guarded by its own
 * permission at the route level (`web.php`), not re-checked here.
 */
final readonly class AdminServiceController
{
    public function __construct(
        private ListServicesHandler $listServices,
        private GetServiceHandler $getService,
        private CreateServiceHandler $createService,
        private UpdateServiceHandler $updateService,
        private DeleteServiceHandler $deleteService,
        private RestoreServiceHandler $restoreService,
        private BulkDeleteServiceHandler $bulkDeleteService,
        private BulkRestoreServiceHandler $bulkRestoreService,
        private ExportPort $export,
    ) {}

    /**
     * The table fetches its own rows via Pinia Colada against `index()`
     * below, so the page itself carries no server-rendered props.
     */
    public function page(): InertiaResponse
    {
        return Inertia::render('services/Index');
    }

    public function index(ServiceFilterData $filters): JsonResponse
    {
        return response()->json($this->listServices->handle($filters));
    }

    /**
     * Streams the filtered catalog as CSV / Excel / PDF. Reuses the SAME
     * `ServiceEloquentModel::applyFilters()` the list query runs (DRY) minus
     * pagination, so an export is exactly what the table currently shows. The
     * date-range invariant (`date_from` ≤ `date_to`) is enforced by
     * {@see ServiceFilterData}; an unknown `format` is a 422.
     */
    public function export(Request $request, ServiceFilterData $filters): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $rows = ServiceEloquentModel::query()
            ->applyFilters($filters)
            ->select(['id', 'uuid', 'name', 'slug', 'description', 'is_active', 'sort_order', 'created_at', 'deleted_at'])
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'services.pdf',
                'exports.pdf.services',
                [
                    'rows' => $rows->map(ServiceExportTransformer::toRow(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "services.{$format}",
                ServiceExportTransformer::headers(),
                $rows->map(ServiceExportTransformer::toRow(...)),
            ),
        };
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->createService->handle(
            $request->validated(),
            (int) $request->user()?->getAuthIdentifier(),
        );

        return response()->json($service, 201);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json($this->getService->handle($uuid));
    }

    public function update(string $uuid, UpdateServiceRequest $request): JsonResponse
    {
        return response()->json($this->updateService->handle($uuid, $request->validated()));
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->deleteService->handle($uuid);

        return response()->json(status: 204);
    }

    public function restore(string $uuid): JsonResponse
    {
        return response()->json($this->restoreService->handle($uuid));
    }

    public function bulkDelete(BulkDeleteServiceRequest $request): JsonResponse
    {
        $deleted = $this->bulkDeleteService->handle($request->validated('uuids'));

        return response()->json(['deleted' => $deleted]);
    }

    public function bulkRestore(BulkRestoreServiceRequest $request): JsonResponse
    {
        $restored = $this->bulkRestoreService->handle($request->validated('uuids'));

        return response()->json(['restored' => $restored]);
    }
}
