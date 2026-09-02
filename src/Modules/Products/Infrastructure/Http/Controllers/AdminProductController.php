<?php

declare(strict_types=1);

namespace Modules\Products\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Products\Application\Commands\BulkDeleteProductsHandler;
use Modules\Products\Application\Commands\BulkRestoreProductsHandler;
use Modules\Products\Application\Commands\CreateProductHandler;
use Modules\Products\Application\Commands\DeleteProductHandler;
use Modules\Products\Application\Commands\RestoreProductHandler;
use Modules\Products\Application\Commands\UpdateProductHandler;
use Modules\Products\Application\DTOs\ProductData;
use Modules\Products\Application\DTOs\ProductFilterData;
use Modules\Products\Application\DTOs\StoreProductData;
use Modules\Products\Application\Queries\GetProductHandler;
use Modules\Products\Application\Queries\ListProductsHandler;
use Modules\Products\Infrastructure\Http\Export\ProductExportTransformer;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Shared\Application\DTOs\BulkUuidsData;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The catalog admin surface: one Inertia page (`page()`) plus the JSON data
 * endpoints it consumes under `/data/admin/products` (Controller Fusion Rule —
 * one entity, one permission set). Each method is guarded by its own permission
 * at the route level (`web.php`), not re-checked here.
 */
final readonly class AdminProductController
{
    public function __construct(
        private ListProductsHandler $listProducts,
        private GetProductHandler $getProduct,
        private CreateProductHandler $createProduct,
        private UpdateProductHandler $updateProduct,
        private DeleteProductHandler $deleteProduct,
        private RestoreProductHandler $restoreProduct,
        private BulkDeleteProductsHandler $bulkDeleteProducts,
        private BulkRestoreProductsHandler $bulkRestoreProducts,
        private ExportPort $export,
    ) {}

    /**
     * The table fetches its own rows via Pinia Colada against `index()`, so the
     * page itself carries no server-rendered props.
     */
    public function page(): InertiaResponse
    {
        return Inertia::render('products/Index');
    }

    public function index(ProductFilterData $filters): JsonResponse
    {
        return response()->json(
            $this->listProducts->handle($filters, $filters->perPage),
        );
    }

    /**
     * Streams the filtered catalog as CSV / Excel / PDF, reusing the SAME
     * `ProductEloquentModel::applyFilters()` the list query runs (DRY) minus
     * pagination. An unknown `format` is a 422.
     */
    public function export(Request $request, ProductFilterData $filters): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $rows = ProductEloquentModel::query()
            ->applyFilters($filters)
            ->with('client:id,uuid,client_name')
            ->select([
                'id', 'uuid', 'client_id', 'type', 'title', 'price', 'currency',
                'default_unit', 'status', 'total_hours', 'total_sessions',
                'modality', 'start_date', 'created_at', 'deleted_at',
            ])
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'products.pdf',
                'exports.pdf.products',
                [
                    'rows' => $rows->map(ProductExportTransformer::toRow(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "products.{$format}",
                ProductExportTransformer::headers(),
                $rows->map(ProductExportTransformer::toRow(...)),
            ),
        };
    }

    public function store(Request $request, StoreProductData $data): JsonResponse
    {
        $product = $this->createProduct->handle($data, (int) $request->user()?->getAuthIdentifier());

        return response()->json(ProductData::fromModel($product->load('client:id,uuid,client_name')), 201);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json(ProductData::fromModel($this->getProduct->handle($uuid)));
    }

    public function update(string $uuid, StoreProductData $data): JsonResponse
    {
        $product = $this->updateProduct->handle($this->getProduct->handle($uuid), $data);

        return response()->json(ProductData::fromModel($product->load('client:id,uuid,client_name')));
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->deleteProduct->handle($uuid);

        return response()->json(status: 204);
    }

    public function restore(string $uuid): JsonResponse
    {
        $this->restoreProduct->handle($uuid);

        return response()->json(ProductData::fromModel($this->getProduct->handle($uuid)));
    }

    public function bulkDelete(BulkUuidsData $data): JsonResponse
    {
        return response()->json(['deleted' => $this->bulkDeleteProducts->handle($data)]);
    }

    public function bulkRestore(BulkUuidsData $data): JsonResponse
    {
        return response()->json(['restored' => $this->bulkRestoreProducts->handle($data)]);
    }
}
