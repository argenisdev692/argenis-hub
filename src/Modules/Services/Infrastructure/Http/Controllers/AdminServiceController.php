<?php

declare(strict_types=1);

namespace Modules\Services\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
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
use Modules\Services\Infrastructure\Http\Requests\BulkDeleteServiceRequest;
use Modules\Services\Infrastructure\Http\Requests\BulkRestoreServiceRequest;
use Modules\Services\Infrastructure\Http\Requests\StoreServiceRequest;
use Modules\Services\Infrastructure\Http\Requests\UpdateServiceRequest;

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
