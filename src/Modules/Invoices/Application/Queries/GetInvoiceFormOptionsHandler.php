<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Queries;

use App\Models\CompanyData;
use Modules\Invoices\Domain\Enums\InvoiceItemKind;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;
use Shared\Domain\Enums\BillingUnit;

/**
 * Catalog props for the invoice create form (clients, services, products,
 * payment accounts, enum vocabularies, notes).
 *
 * Payment accounts are sent unfiltered on purpose: the form filters them
 * client-side as the currency `<Select>` changes, so switching USD → EUR does
 * not need a round trip.
 */
final readonly class GetInvoiceFormOptionsHandler
{
    public function __construct(
        private InvoiceRepositoryPort $invoices,
    ) {}

    /**
     * @return array{
     *     clients: list<array{uuid: string, client_name: string, tax_id: string|null, nif: string|null, address: string|null, email: string|null, country: string|null, country_code: string|null}>,
     *     services: list<array{uuid: string, name: string, description: string|null}>,
     *     products: list<array{uuid: string, title: string, description: string|null, price: mixed, currency: string, type: string, default_unit: string, total_hours: float|null}>,
     *     paymentAccounts: list<array{uuid: string, method: string, currency: string|null, label: string, is_default: bool}>,
     *     paymentMethods: list<string>,
     *     itemKinds: list<string>,
     *     billingUnits: list<string>,
     *     defaultNotes: string|null,
     *     issuerCountry: string
     * }
     */
    public function handle(): array
    {
        $company = CompanyData::query()->orderBy('id')->first();
        $issuerCountry = trim((string) ($company?->country ?? ''));
        if ($issuerCountry === '') {
            $issuerCountry = 'Portugal';
        }

        return [
            'clients' => $this->invoices->listActiveClientsForForm(),
            // The Services module ships no repository port (Repository
            // Optionality Rule), so the catalog is read straight off the model.
            'services' => ServiceEloquentModel::query()
                ->active()
                ->orderBy('sort_order')
                ->select(['uuid', 'name', 'description'])
                ->get()
                ->map(static fn (ServiceEloquentModel $service): array => [
                    'uuid' => $service->uuid,
                    'name' => $service->name,
                    'description' => $service->description,
                ])->values()->all(),
            'products' => $this->invoices->listPublishedProductsForForm(),
            'paymentAccounts' => $this->invoices->listPaymentAccountsForForm(),
            'paymentMethods' => PaymentMethod::values(),
            'itemKinds' => InvoiceItemKind::values(),
            'billingUnits' => BillingUnit::values(),
            'defaultNotes' => $this->invoices->defaultInvoiceNotes(),
            'issuerCountry' => $issuerCountry,
        ];
    }
}
