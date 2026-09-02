<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Domain\Enums\InvoiceItemKind;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceItemEloquentModel;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;
use Shared\Domain\Enums\BillingUnit;

uses(RefreshDatabase::class);

/**
 * Every FK in this project carries BOTH sides of the relation
 * (`ARCHITECTURE-PHP/SKILL-SIMPLE-CRUD.md` — Bidirectional Eloquent relations).
 * A one-directional FK is a FAIL, so each pair is asserted from both ends.
 *
 * @return array{
 *     user: User,
 *     client: ClientEloquentModel,
 *     service: ServiceEloquentModel,
 *     product: ProductEloquentModel,
 *     account: PaymentAccountEloquentModel,
 *     invoice: InvoiceEloquentModel
 * }
 */
function billingGraph(): array
{
    $user = User::factory()->create();
    $client = ClientEloquentModel::factory()->active()->create(['user_id' => $user->id]);
    $service = ServiceEloquentModel::factory()->create(['user_id' => $user->id]);
    $product = ProductEloquentModel::factory()->create([
        'user_id' => $user->id,
        'client_id' => $client->id,
    ]);
    $account = PaymentAccountEloquentModel::factory()->create(['user_id' => $user->id]);

    $invoice = InvoiceEloquentModel::factory()->create([
        'user_id' => $user->id,
        'client_id' => $client->id,
        'product_id' => $product->id,
        'payment_account_id' => $account->id,
        'payment_details_json' => $account->toSnapshot(),
    ]);

    $invoice->items()->createMany([
        [
            'service_id' => $service->id,
            'kind' => InvoiceItemKind::Service,
            'unit' => BillingUnit::Unit,
            'sort_order' => 0,
            'title' => 'Web development',
            'quantity' => 1,
            'unit_price' => 35,
            'amount' => 35,
        ],
        [
            'product_id' => $product->id,
            'kind' => InvoiceItemKind::Course,
            'unit' => BillingUnit::Hour,
            'sort_order' => 1,
            'title' => 'Tabnine AI course',
            'quantity' => 25,
            'unit_price' => 52,
            'amount' => 1300,
        ],
    ]);

    return compact('user', 'client', 'service', 'product', 'account', 'invoice');
}

it('resolves every invoice belongsTo', function (): void {
    ['invoice' => $invoice, 'user' => $user, 'client' => $client] = billingGraph();
    $invoice->refresh();

    expect($invoice->user->id)->toBe($user->id)
        ->and($invoice->client->id)->toBe($client->id)
        ->and($invoice->product)->not->toBeNull()
        ->and($invoice->paymentAccount)->not->toBeNull()
        ->and($invoice->items)->toHaveCount(2);
});

it('resolves every invoice item belongsTo', function (): void {
    ['invoice' => $invoice, 'service' => $service, 'product' => $product] = billingGraph();

    $items = $invoice->items()->with(['invoice', 'service', 'product'])->get();

    expect($items[0]->invoice->id)->toBe($invoice->id)
        ->and($items[0]->service->id)->toBe($service->id)
        ->and($items[0]->product)->toBeNull()
        ->and($items[1]->product->id)->toBe($product->id)
        ->and($items[1]->service)->toBeNull();
});

it('resolves the user inverse of every relation it owns', function (): void {
    ['user' => $user] = billingGraph();

    expect($user->clients)->toHaveCount(1)
        ->and($user->services)->toHaveCount(1)
        ->and($user->products)->toHaveCount(1)
        ->and($user->paymentAccounts)->toHaveCount(1)
        ->and($user->invoices)->toHaveCount(1);
});

it('resolves the client inverse of invoices and products', function (): void {
    ['client' => $client] = billingGraph();

    expect($client->invoices)->toHaveCount(1)
        ->and($client->products)->toHaveCount(1);
});

it('resolves the product inverse of invoices and invoice items', function (): void {
    ['product' => $product] = billingGraph();

    expect($product->invoices)->toHaveCount(1)
        ->and($product->invoiceItems)->toHaveCount(1)
        ->and($product->user)->not->toBeNull()
        ->and($product->client)->not->toBeNull();
});

it('resolves the service inverse of invoice items', function (): void {
    ['service' => $service] = billingGraph();

    expect($service->invoiceItems)->toHaveCount(1);
});

it('resolves the payment account inverse of invoices', function (): void {
    ['account' => $account] = billingGraph();

    expect($account->invoices)->toHaveCount(1)->and($account->user)->not->toBeNull();
});

it('keeps the invoice line when its catalog row is deleted', function (): void {
    ['invoice' => $invoice, 'service' => $service, 'product' => $product] = billingGraph();

    // nullOnDelete on both FKs: an issued invoice must survive catalog cleanup.
    $service->forceDelete();
    $product->forceDelete();

    $items = $invoice->refresh()->items;

    expect($items)->toHaveCount(2)
        ->and($items[0]->service_id)->toBeNull()
        ->and($items[1]->product_id)->toBeNull()
        ->and($items[1]->title)->toBe('Tabnine AI course');
});

it('keeps the payment snapshot when the account is force deleted', function (): void {
    ['invoice' => $invoice, 'account' => $account] = billingGraph();

    $account->forceDelete();
    $invoice->refresh();

    expect($invoice->payment_account_id)->toBeNull()
        ->and($invoice->payment_details_json['iban'])->toBe('PT50003600119910006305349');
});

it('refuses to hard delete a client that still has invoices', function (): void {
    ['client' => $client] = billingGraph();

    // restrictOnDelete on invoices.client_id.
    expect(fn () => $client->forceDelete())->toThrow(QueryException::class);
});

it('cascades invoice items when the invoice is force deleted', function (): void {
    ['invoice' => $invoice] = billingGraph();

    $invoice->forceDelete();

    expect(InvoiceItemEloquentModel::query()->where('invoice_id', $invoice->id)->count())->toBe(0);
});
