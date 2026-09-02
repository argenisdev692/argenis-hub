<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\PaymentAccounts\Application\Commands\BulkDeletePaymentAccountsHandler;
use Modules\PaymentAccounts\Application\Commands\BulkRestorePaymentAccountsHandler;
use Modules\PaymentAccounts\Application\Commands\CreatePaymentAccountHandler;
use Modules\PaymentAccounts\Application\Commands\DeletePaymentAccountHandler;
use Modules\PaymentAccounts\Application\Commands\RestorePaymentAccountHandler;
use Modules\PaymentAccounts\Application\Commands\UpdatePaymentAccountHandler;
use Modules\PaymentAccounts\Application\DTOs\PaymentAccountData;
use Modules\PaymentAccounts\Application\DTOs\PaymentAccountFilterData;
use Modules\PaymentAccounts\Application\DTOs\StorePaymentAccountData;
use Modules\PaymentAccounts\Application\Queries\GetPaymentAccountHandler;
use Modules\PaymentAccounts\Application\Queries\ListPaymentAccountsHandler;
use Modules\PaymentAccounts\Infrastructure\Http\Export\PaymentAccountExportTransformer;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;
use Shared\Application\DTOs\BulkUuidsData;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The settlement-rails admin surface: one Inertia page (`page()`) plus the JSON
 * data endpoints it consumes under `/data/admin/payment-accounts` (Controller
 * Fusion Rule). Each method is guarded by its own permission at the route level
 * (`web.php`), not re-checked here.
 */
final readonly class AdminPaymentAccountController
{
    public function __construct(
        private ListPaymentAccountsHandler $listAccounts,
        private GetPaymentAccountHandler $getAccount,
        private CreatePaymentAccountHandler $createAccount,
        private UpdatePaymentAccountHandler $updateAccount,
        private DeletePaymentAccountHandler $deleteAccount,
        private RestorePaymentAccountHandler $restoreAccount,
        private BulkDeletePaymentAccountsHandler $bulkDeleteAccounts,
        private BulkRestorePaymentAccountsHandler $bulkRestoreAccounts,
        private ExportPort $export,
    ) {}

    public function page(): InertiaResponse
    {
        return Inertia::render('payment-accounts/Index');
    }

    public function index(PaymentAccountFilterData $filters): JsonResponse
    {
        return response()->json($this->listAccounts->handle($filters, $filters->perPage));
    }

    public function export(Request $request, PaymentAccountFilterData $filters): StreamedResponse|Response
    {
        $format = (string) $request->string('format', 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422);

        $rows = PaymentAccountEloquentModel::query()
            ->applyFilters($filters)
            ->select([
                'id', 'uuid', 'label', 'method', 'currency', 'beneficiary', 'bank_name',
                'iban', 'account_number', 'is_default', 'is_active', 'created_at', 'deleted_at',
            ])
            ->lazy();

        return match ($format) {
            'pdf' => $this->export->pdf(
                'payment-accounts.pdf',
                'exports.pdf.payment-accounts',
                [
                    'rows' => $rows->map(PaymentAccountExportTransformer::toRow(...)),
                    'generatedAt' => now()->format('F j, Y H:i'),
                ],
            ),
            default => $this->export->tabular(
                "payment-accounts.{$format}",
                PaymentAccountExportTransformer::headers(),
                $rows->map(PaymentAccountExportTransformer::toRow(...)),
            ),
        };
    }

    public function store(Request $request, StorePaymentAccountData $data): JsonResponse
    {
        $account = $this->createAccount->handle($data, (int) $request->user()?->getAuthIdentifier());

        return response()->json(PaymentAccountData::fromModel($account), 201);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json(PaymentAccountData::fromModel($this->getAccount->handle($uuid)));
    }

    public function update(string $uuid, StorePaymentAccountData $data): JsonResponse
    {
        $account = $this->updateAccount->handle($this->getAccount->handle($uuid), $data);

        return response()->json(PaymentAccountData::fromModel($account));
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->deleteAccount->handle($uuid);

        return response()->json(status: 204);
    }

    public function restore(string $uuid): JsonResponse
    {
        $this->restoreAccount->handle($uuid);

        return response()->json(PaymentAccountData::fromModel($this->getAccount->handle($uuid)));
    }

    public function bulkDelete(BulkUuidsData $data): JsonResponse
    {
        return response()->json(['deleted' => $this->bulkDeleteAccounts->handle($data)]);
    }

    public function bulkRestore(BulkUuidsData $data): JsonResponse
    {
        return response()->json(['restored' => $this->bulkRestoreAccounts->handle($data)]);
    }
}
