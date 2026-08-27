<?php

declare(strict_types=1);

namespace Modules\Clients\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Clients\Application\Commands\BulkDeleteClientHandler;
use Modules\Clients\Application\Commands\BulkRestoreClientHandler;
use Modules\Clients\Application\Commands\CreateClientHandler;
use Modules\Clients\Application\Commands\DeleteClientHandler;
use Modules\Clients\Application\Commands\RestoreClientHandler;
use Modules\Clients\Application\Commands\UpdateClientHandler;
use Modules\Clients\Application\DTOs\ClientFilterData;
use Modules\Clients\Application\Queries\GetClientHandler;
use Modules\Clients\Application\Queries\ListClientsHandler;
use Modules\Clients\Infrastructure\Http\Export\ClientExportTransformer;
use Modules\Clients\Infrastructure\Http\Requests\BulkDeleteClientRequest;
use Modules\Clients\Infrastructure\Http\Requests\BulkRestoreClientRequest;
use Modules\Clients\Infrastructure\Http\Requests\StoreClientRequest;
use Modules\Clients\Infrastructure\Http\Requests\UpdateClientRequest;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The CRM admin surface: one Inertia page (`page()`) plus the JSON data
 * endpoints it consumes under `/data/admin/clients` (Controller Fusion Rule —
 * the two flows share one entity and one permission set). Each method is
 * guarded by its own permission at the route level (`web.php`), not re-checked
 * here.
 */
final readonly class AdminClientController
{
    public function __construct(
        private ListClientsHandler $listClients,
        private GetClientHandler $getClient,
        private CreateClientHandler $createClient,
        private UpdateClientHandler $updateClient,
        private DeleteClientHandler $deleteClient,
        private RestoreClientHandler $restoreClient,
        private BulkDeleteClientHandler $bulkDeleteClient,
        private BulkRestoreClientHandler $bulkRestoreClient,
        private ExportPort $export,
    ) {}

    /**
     * The table fetches its own rows via Pinia Colada against `index()`, so the
     * page itself carries no server-rendered props.
     */
    public function page(): InertiaResponse
    {
        return Inertia::render('clients/Index');
    }

    public function index(ClientFilterData $filters): JsonResponse
    {
        return response()->json($this->listClients->handle($filters));
    }

    /**
     * Streams the filtered client list as CSV / Excel / PDF. Reuses the SAME
     * `ClientEloquentModel::applyFilters()` the list query runs (DRY) minus
     * pagination. The date-range invariant (`date_from` ≤ `date_to`) is
     * enforced by {@see ClientFilterData}; an unknown `format` is a 422.
     */
    public function export(Request $request, ClientFilterData $filters): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $rows = ClientEloquentModel::query()
            ->applyFilters($filters)
            ->with('user:id,first_name,last_name')
            ->select(['id', 'uuid', 'user_id', 'client_name', 'email', 'status', 'phone', 'created_at', 'deleted_at'])
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'clients.pdf',
                'exports.pdf.clients',
                [
                    'rows' => $rows->map(ClientExportTransformer::toRow(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "clients.{$format}",
                ClientExportTransformer::headers(),
                $rows->map(ClientExportTransformer::toRow(...)),
            ),
        };
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = $this->createClient->handle(
            $request->validated(),
            (int) $request->user()?->getAuthIdentifier(),
        );

        return response()->json($client, 201);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json($this->getClient->handle($uuid));
    }

    public function update(string $uuid, UpdateClientRequest $request): JsonResponse
    {
        return response()->json($this->updateClient->handle($uuid, $request->validated()));
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->deleteClient->handle($uuid);

        return response()->json(status: 204);
    }

    public function restore(string $uuid): JsonResponse
    {
        return response()->json($this->restoreClient->handle($uuid));
    }

    public function bulkDelete(BulkDeleteClientRequest $request): JsonResponse
    {
        $deleted = $this->bulkDeleteClient->handle($request->validated('uuids'));

        return response()->json(['deleted' => $deleted]);
    }

    public function bulkRestore(BulkRestoreClientRequest $request): JsonResponse
    {
        $restored = $this->bulkRestoreClient->handle($request->validated('uuids'));

        return response()->json(['restored' => $restored]);
    }
}
