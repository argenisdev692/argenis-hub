<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Domain\Enums\InvoiceItemKind;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Shared\Domain\Enums\BillingUnit;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Queue::fake();
});

function billingAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

/**
 * @return array<string, mixed>
 */
function invoicePayload(ClientEloquentModel $client, array $overrides = []): array
{
    return [
        'client_uuid' => $client->uuid,
        'invoice_number' => '004/2026',
        'issue_date' => '2026-03-10',
        'due_date' => '2026-04-10',
        'currency' => 'EUR',
        'tax_mode' => 'EXEMPT',
        'tax_label' => 'IVA',
        'is_paid' => false,
        'items' => [[
            'title' => 'Tabnine AI para Desarrolladores',
            'quantity' => 25,
            'unit_price' => 52,
        ]],
        ...$overrides,
    ];
}

it('bills a course by the hour and links it to the product catalog', function (): void {
    $admin = billingAdmin();
    $client = ClientEloquentModel::factory()->active()->create(['country_code' => 'ES']);
    $course = ProductEloquentModel::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->postJson('/data/admin/invoices', invoicePayload($client, [
        'items' => [[
            'title' => 'Tabnine AI para Desarrolladores',
            'description' => "- Formacion completa en 5 sesiones de 5 horas\n- Fechas: 22, 24, 29, 31 Oct y 7 Nov 2025",
            'kind' => InvoiceItemKind::Course->value,
            'unit' => BillingUnit::Hour->value,
            'product_uuid' => $course->uuid,
            'quantity' => 25,
            'unit_price' => 52,
        ]],
    ]))->assertCreated();

    $item = InvoiceEloquentModel::query()->firstOrFail()->items->first();

    expect($item->kind)->toBe(InvoiceItemKind::Course)
        ->and($item->unit)->toBe(BillingUnit::Hour)
        ->and($item->product_id)->toBe($course->id)
        ->and($item->amount)->toBe('1300.00')
        ->and($item->description)->toContain('5 sesiones');
});

it('rejects a course or video line that is not backed by a catalog product', function (InvoiceItemKind $kind): void {
    $admin = billingAdmin();
    $client = ClientEloquentModel::factory()->active()->create(['country_code' => 'ES']);

    $this->actingAs($admin)->postJson('/data/admin/invoices', invoicePayload($client, [
        'items' => [[
            'title' => 'Training with no catalog row behind it',
            'kind' => $kind->value,
            'unit' => BillingUnit::Hour->value,
            'quantity' => 25,
            'unit_price' => 52,
        ]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.product_uuid');

    expect(InvoiceEloquentModel::query()->count())->toBe(0);
})->with([
    'course' => InvoiceItemKind::Course,
    'video' => InvoiceItemKind::Video,
]);

it('still accepts a custom line with no product', function (): void {
    $admin = billingAdmin();
    $client = ClientEloquentModel::factory()->active()->create(['country_code' => 'ES']);

    $this->actingAs($admin)->postJson('/data/admin/invoices', invoicePayload($client, [
        'items' => [[
            'title' => 'Web Development - Remote Service',
            'kind' => InvoiceItemKind::Custom->value,
            'unit' => BillingUnit::Unit->value,
            'quantity' => 1,
            'unit_price' => 35,
        ]],
    ]))->assertCreated();
});

it('bills a video course and a web service on the same invoice', function (): void {
    $admin = billingAdmin();
    $client = ClientEloquentModel::factory()->active()->create(['country_code' => 'ES']);
    $video = ProductEloquentModel::factory()->videoCourse()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->postJson('/data/admin/invoices', invoicePayload($client, [
        'items' => [
            [
                'title' => 'Pildoras de Video Microsoft 365 Copilot',
                'kind' => InvoiceItemKind::Video->value,
                'unit' => BillingUnit::Hour->value,
                'product_uuid' => $video->uuid,
                'quantity' => 6,
                'unit_price' => 52,
                'sort_order' => 0,
            ],
            [
                'title' => 'Web Development - Remote Service',
                'kind' => InvoiceItemKind::Custom->value,
                'unit' => BillingUnit::Unit->value,
                'quantity' => 1,
                'unit_price' => 35,
                'sort_order' => 1,
            ],
        ],
    ]))->assertCreated();

    $invoice = InvoiceEloquentModel::query()->firstOrFail();

    expect($invoice->total)->toBe('347.00')
        ->and($invoice->items->pluck('kind')->all())
        ->toBe([InvoiceItemKind::Video, InvoiceItemKind::Custom]);
});

it('stores a fractional hour quantity without rounding it away', function (): void {
    $admin = billingAdmin();
    $client = ClientEloquentModel::factory()->active()->create(['country_code' => 'ES']);
    $course = ProductEloquentModel::factory()->classroom()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->postJson('/data/admin/invoices', invoicePayload($client, [
        'items' => [[
            'title' => 'Sesion 7 - Proyecto Final',
            'kind' => InvoiceItemKind::Course->value,
            'unit' => BillingUnit::Hour->value,
            'product_uuid' => $course->uuid,
            'quantity' => 3.5,
            'unit_price' => 52,
        ]],
    ]))->assertCreated();

    $item = InvoiceEloquentModel::query()->firstOrFail()->items->first();

    expect($item->quantity)->toBe('3.50')->and($item->amount)->toBe('182.00');
});

it('defaults an item to a custom unit line when kind and unit are omitted', function (): void {
    $admin = billingAdmin();
    $client = ClientEloquentModel::factory()->active()->create();

    $this->actingAs($admin)->postJson('/data/admin/invoices', invoicePayload($client))->assertCreated();

    $item = InvoiceEloquentModel::query()->firstOrFail()->items->first();

    expect($item->kind)->toBe(InvoiceItemKind::Custom)->and($item->unit)->toBe(BillingUnit::Unit);
});

it('rejects an item pointing at a product that does not exist', function (): void {
    $admin = billingAdmin();
    $client = ClientEloquentModel::factory()->active()->create();

    $this->actingAs($admin)->postJson('/data/admin/invoices', invoicePayload($client, [
        'items' => [[
            'title' => 'Ghost course',
            'kind' => InvoiceItemKind::Course->value,
            'unit' => BillingUnit::Hour->value,
            'product_uuid' => '0198c0f6-0000-7000-8000-000000000000',
            'quantity' => 1,
            'unit_price' => 10,
        ]],
    ]))->assertJsonValidationErrors('items.0.product_uuid');
});

it('snapshots the remitly account on a paid usd invoice', function (): void {
    $admin = billingAdmin();
    $client = ClientEloquentModel::factory()->active()->create(['country_code' => 'US']);
    $remitly = PaymentAccountEloquentModel::factory()->remitlyUsd()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->postJson('/data/admin/invoices', invoicePayload($client, [
        'currency' => 'USD',
        'is_paid' => true,
        'payment_method' => PaymentMethod::Remitly->value,
        'payment_account_uuid' => $remitly->uuid,
        'transfer_number' => 'R13 188 385 794',
        'payment_date' => '2026-03-12',
        'amount_received' => 1300,
    ]))->assertCreated();

    $invoice = InvoiceEloquentModel::query()->firstOrFail();

    expect($invoice->payment_method)->toBe(PaymentMethod::Remitly)
        ->and($invoice->payment_account_id)->toBe($remitly->id)
        ->and($invoice->payment_details_json['label'])->toBe('Remitly USD')
        ->and($invoice->transfer_number)->toBe('R13 188 385 794');
});

it('keeps the snapshot when the account is edited afterwards', function (): void {
    $admin = billingAdmin();
    $client = ClientEloquentModel::factory()->active()->create(['country_code' => 'ES']);
    $account = PaymentAccountEloquentModel::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->postJson('/data/admin/invoices', invoicePayload($client, [
        'payment_account_uuid' => $account->uuid,
    ]))->assertCreated();

    $account->update(['iban' => 'PT50000200000000000000009']);

    expect(InvoiceEloquentModel::query()->firstOrFail()->payment_details_json['iban'])
        ->toBe('PT50003600119910006305349');
});

it('resolves the default account for the invoice currency when none is chosen', function (): void {
    $admin = billingAdmin();
    $client = ClientEloquentModel::factory()->active()->create(['country_code' => 'US']);
    PaymentAccountEloquentModel::factory()->create(['user_id' => $admin->id]);
    $usd = PaymentAccountEloquentModel::factory()->remitlyUsd()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->postJson('/data/admin/invoices', invoicePayload($client, ['currency' => 'USD']))
        ->assertCreated();

    expect(InvoiceEloquentModel::query()->firstOrFail()->payment_account_id)->toBe($usd->id);
});

it('settles usd by bank transfer when that account is chosen', function (): void {
    $admin = billingAdmin();
    $client = ClientEloquentModel::factory()->active()->create(['country_code' => 'US']);
    PaymentAccountEloquentModel::factory()->remitlyUsd()->create(['user_id' => $admin->id]);
    $wire = PaymentAccountEloquentModel::factory()->bankTransferUsd()->notDefault()
        ->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->postJson('/data/admin/invoices', invoicePayload($client, [
        'currency' => 'USD',
        'is_paid' => true,
        'payment_method' => PaymentMethod::BankTransfer->value,
        'payment_account_uuid' => $wire->uuid,
        'payment_date' => '2026-03-12',
        'amount_received' => 1300,
    ]))->assertCreated();

    $invoice = InvoiceEloquentModel::query()->firstOrFail();

    expect($invoice->payment_method)->toBe(PaymentMethod::BankTransfer)
        ->and($invoice->payment_details_json['routing_number'])->toBe('021000021');
});

it('rejects an unknown payment method', function (): void {
    $admin = billingAdmin();
    $client = ClientEloquentModel::factory()->active()->create();

    $this->actingAs($admin)->postJson('/data/admin/invoices', invoicePayload($client, [
        'is_paid' => true,
        'payment_method' => 'BITCOIN',
        'payment_date' => '2026-03-12',
        'amount_received' => 1300,
    ]))->assertJsonValidationErrors('payment_method');
});
