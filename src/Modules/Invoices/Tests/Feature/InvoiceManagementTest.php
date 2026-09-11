<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Application\Support\InvoiceClientBilling;
use Modules\Invoices\Application\Support\InvoicePdfViewAssembler;
use Modules\Invoices\Domain\Enums\TaxMode;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceItemEloquentModel;
use Modules\Invoices\Infrastructure\Queue\GenerateInvoicePdfJob;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function invoiceManager(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

/**
 * @return array<string, mixed>
 */
function managementPayload(ClientEloquentModel $client, array $overrides = []): array
{
    return [
        'client_uuid' => $client->uuid,
        'invoice_number' => '001/2026',
        'issue_date' => '2026-03-10',
        'due_date' => '2026-03-15',
        'currency' => 'USD',
        'tax_mode' => 'EXEMPT',
        'tax_rate' => null,
        'tax_label' => 'IVA',
        'is_paid' => false,
        'payment_method' => null,
        'transfer_number' => null,
        'payment_date' => null,
        'amount_received' => null,
        'notes' => 'VAT - Reverse Charge: International transaction exempt from VAT.',
        'items' => [
            [
                'title' => 'Website development',
                'description' => 'Corporate website',
                'quantity' => 1,
                'unit_price' => 150,
                'service_uuid' => null,
                'sort_order' => 0,
            ],
            [
                'title' => 'Logo redesign',
                'description' => null,
                'quantity' => 1,
                'unit_price' => 50,
                'service_uuid' => null,
                'sort_order' => 1,
            ],
        ],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return list<array<string, mixed>>
 */
function singleLine(array $overrides = []): array
{
    return [[
        'title' => 'Consulting',
        'description' => null,
        'quantity' => 1,
        'unit_price' => 100,
        'service_uuid' => null,
        'sort_order' => 0,
        ...$overrides,
    ]];
}

it('creates an invoice with computed totals', function (): void {
    Queue::fake();

    $admin = invoiceManager();
    $client = ClientEloquentModel::factory()->active()->create([
        'client_name' => 'AquaShield',
        'tax_id' => '36-5164436',
    ]);

    $this->actingAs($admin)
        ->postJson('/data/admin/invoices', managementPayload($client))
        ->assertCreated();

    $invoice = InvoiceEloquentModel::query()->where('invoice_number', '001/2026')->firstOrFail();

    expect($invoice->user_id)->toBe($admin->id)
        ->and($invoice->client_id)->toBe($client->id)
        ->and($invoice->sequence)->toBe(1)
        ->and($invoice->year)->toBe(2026)
        ->and($invoice->tax_mode)->toBe(TaxMode::Exempt)
        ->and($invoice->subtotal)->toBe('200.00')
        ->and($invoice->tax_amount)->toBe('0.00')
        ->and($invoice->total)->toBe('200.00')
        ->and($invoice->currency)->toBe('USD')
        ->and($invoice->items)->toHaveCount(2);

    Queue::assertPushed(GenerateInvoicePdfJob::class);
});

it('persists eur currency when requested', function (): void {
    Queue::fake();

    $client = ClientEloquentModel::factory()->active()->create(['country_code' => 'ES']);

    $this->actingAs(invoiceManager())
        ->postJson('/data/admin/invoices', managementPayload($client, [
            'invoice_number' => '020/2026',
            'currency' => 'EUR',
        ]))
        ->assertCreated();

    expect(InvoiceEloquentModel::query()->where('invoice_number', '020/2026')->firstOrFail()->currency)
        ->toBe('EUR');
});

it('updates the currency and the pdf assembler symbol follows it', function (): void {
    $admin = invoiceManager();
    $client = ClientEloquentModel::factory()->active()->create(['country_code' => 'US']);
    $invoice = InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'client_id' => $client->id,
        'invoice_number' => '021/2026',
        'sequence' => 21,
        'year' => 2026,
        'currency' => 'EUR',
        'subtotal' => 10,
        'tax_amount' => 0,
        'total' => 10,
    ]);
    InvoiceItemEloquentModel::query()->create([
        'invoice_id' => $invoice->id,
        'sort_order' => 0,
        'title' => 'Old',
        'quantity' => 1,
        'unit_price' => 10,
        'amount' => 10,
    ]);

    $this->actingAs($admin)
        ->putJson("/data/admin/invoices/{$invoice->uuid}", managementPayload($client, [
            'invoice_number' => '021/2026',
            'currency' => 'USD',
        ]))
        ->assertOk();

    $invoice->refresh()->load('client');
    $pdf = (new InvoicePdfViewAssembler)->assemble($invoice, ['country' => 'Portugal', 'country_code' => 'PT']);

    expect($invoice->currency)->toBe('USD')
        ->and($pdf['currency_symbol'])->toBe('$');
});

it('shows items, currency and notes for the edit form to hydrate from', function (): void {
    $admin = invoiceManager();
    $client = ClientEloquentModel::factory()->active()->create(['country_code' => 'US']);
    $invoice = InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'client_id' => $client->id,
        'invoice_number' => '022/2026',
        'sequence' => 22,
        'year' => 2026,
        'currency' => 'USD',
        'notes' => 'Stored fiscal notice',
        'additional_notes' => 'Extra note',
    ]);
    InvoiceItemEloquentModel::query()->create([
        'invoice_id' => $invoice->id,
        'sort_order' => 0,
        'title' => 'Consulting',
        'description' => 'Hourly',
        'quantity' => 2,
        'unit_price' => 50,
        'amount' => 100,
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/data/admin/invoices/{$invoice->uuid}")
        ->assertOk()
        ->assertJsonPath('currency', 'USD')
        ->assertJsonPath('tax_mode', TaxMode::Exempt->value)
        ->assertJsonPath('notes', 'Stored fiscal notice')
        ->assertJsonPath('additional_notes', 'Extra note')
        ->assertJsonPath('items.0.title', 'Consulting');

    expect((float) $response->json('items.0.quantity'))->toBe(2.0)
        ->and((float) $response->json('items.0.unit_price'))->toBe(50.0);
});

it('links the client for the pdf and the listing', function (): void {
    Queue::fake();

    $client = ClientEloquentModel::factory()->active()->create([
        'country' => 'Spain',
        'country_code' => 'ES',
        'email' => 'billing@client.test',
        'phone' => '+34600111222',
        'nif' => 'B98330335',
        'tax_id' => '0',
    ]);

    $this->actingAs(invoiceManager())
        ->postJson('/data/admin/invoices', managementPayload($client, ['invoice_number' => '002/2026']))
        ->assertCreated();

    $invoice = InvoiceEloquentModel::query()
        ->with('client:id,uuid,client_name,email,phone,tax_id,nif,country,country_code')
        ->where('invoice_number', '002/2026')
        ->firstOrFail();

    expect($invoice->client_id)->toBe($client->id)
        ->and($invoice->client?->country_code)->toBe('ES')
        ->and($invoice->client?->email)->toBe('billing@client.test')
        ->and(InvoiceClientBilling::taxIdForClient($invoice->client))->toBe('B98330335');
});

it('keeps the total equal to the subtotal at a zero percent tax rate', function (): void {
    $client = ClientEloquentModel::factory()->active()->create();

    $this->actingAs(invoiceManager())
        ->postJson('/data/admin/invoices', managementPayload($client, [
            'invoice_number' => '010/2026',
            'tax_mode' => 'PERCENT',
            'tax_rate' => 0,
            'items' => singleLine(),
        ]))
        ->assertCreated();

    $invoice = InvoiceEloquentModel::query()->where('invoice_number', '010/2026')->firstOrFail();

    expect($invoice->tax_mode)->toBe(TaxMode::Percent)
        ->and($invoice->tax_rate)->toBe('0.0000')
        ->and($invoice->subtotal)->toBe('100.00')
        ->and($invoice->tax_amount)->toBe('0.00')
        ->and($invoice->total)->toBe('100.00');
});

it('stores the payment details of a paid invoice', function (): void {
    $client = ClientEloquentModel::factory()->active()->create();

    $this->actingAs(invoiceManager())
        ->postJson('/data/admin/invoices', managementPayload($client, [
            'invoice_number' => '011/2026',
            'is_paid' => true,
            'payment_method' => PaymentMethod::Remitly->value,
            'transfer_number' => 'R20 386 959 937',
            'payment_date' => '2026-05-02',
            'amount_received' => 25,
            'items' => singleLine(['title' => 'Fix', 'unit_price' => 25]),
        ]))
        ->assertCreated();

    $invoice = InvoiceEloquentModel::query()->where('invoice_number', '011/2026')->firstOrFail();

    expect($invoice->is_paid)->toBeTrue()
        ->and($invoice->payment_method)->toBe(PaymentMethod::Remitly)
        ->and($invoice->transfer_number)->toBe('R20 386 959 937')
        ->and($invoice->payment_date?->toDateString())->toBe('2026-05-02')
        ->and($invoice->amount_received)->toBe('25.00');
});

it('requires the payment fields on a paid invoice', function (): void {
    $client = ClientEloquentModel::factory()->active()->create();

    $this->actingAs(invoiceManager())
        ->postJson('/data/admin/invoices', managementPayload($client, [
            'invoice_number' => '012/2026',
            'is_paid' => true,
            'payment_method' => null,
            'payment_date' => null,
            'amount_received' => null,
        ]))
        ->assertJsonValidationErrors(['payment_method', 'payment_date', 'amount_received']);
});

it('applies a percent tax to the total', function (): void {
    $client = ClientEloquentModel::factory()->active()->create();

    $this->actingAs(invoiceManager())
        ->postJson('/data/admin/invoices', managementPayload($client, [
            'invoice_number' => '002/2026',
            'tax_mode' => 'PERCENT',
            'tax_rate' => 23,
            'items' => singleLine(),
        ]))
        ->assertCreated();

    $invoice = InvoiceEloquentModel::query()->where('invoice_number', '002/2026')->firstOrFail();

    expect($invoice->subtotal)->toBe('100.00')
        ->and($invoice->tax_amount)->toBe('23.00')
        ->and($invoice->total)->toBe('123.00');
});

it('rejects an unknown tax mode', function (): void {
    $client = ClientEloquentModel::factory()->active()->create();

    $this->actingAs(invoiceManager())
        ->postJson('/data/admin/invoices', managementPayload($client, ['tax_mode' => 'REDUCED']))
        ->assertJsonValidationErrors('tax_mode');
});

it('suggests the next sequence after the last invoice number', function (): void {
    $admin = invoiceManager();
    InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'invoice_number' => '007/2026',
        'sequence' => 7,
        'year' => 2026,
    ]);

    $this->actingAs($admin)
        ->getJson('/data/admin/invoices/next-number?year=2026')
        ->assertOk()
        ->assertJson([
            'invoice_number' => '008/2026',
            'sequence' => 8,
            'year' => 2026,
        ]);
});

it('reports a free invoice number as available', function (): void {
    $this->actingAs(invoiceManager())
        ->getJson('/data/admin/invoices/check-number?invoice_number=014/2026')
        ->assertOk()
        ->assertJson([
            'available' => true,
            'invoice_number' => '014/2026',
            'invoice' => null,
        ]);
});

it('returns the client name when the invoice number is taken', function (): void {
    $admin = invoiceManager();
    $client = ClientEloquentModel::factory()->active()->create([
        'client_name' => 'Aquashield Restoration LLC',
    ]);
    InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'client_id' => $client->id,
        'invoice_number' => '014/'.now()->year,
        'sequence' => 14,
        'year' => now()->year,
    ]);

    $this->actingAs($admin)
        ->getJson('/data/admin/invoices/check-number?invoice_number=014')
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('invoice_number', '014/'.now()->year)
        ->assertJsonPath('invoice.client_name', 'Aquashield Restoration LLC')
        ->assertJsonPath('invoice.is_suspended', false);
});

it('ignores the invoice being edited when checking its number', function (): void {
    $admin = invoiceManager();
    $client = ClientEloquentModel::factory()->active()->create(['client_name' => 'Self Client']);
    $invoice = InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'client_id' => $client->id,
        'invoice_number' => '014/2026',
        'sequence' => 14,
        'year' => 2026,
    ]);

    $this->actingAs($admin)
        ->getJson("/data/admin/invoices/check-number?invoice_number=014/2026&ignore={$invoice->uuid}")
        ->assertOk()
        ->assertJson([
            'available' => true,
            'invoice' => null,
        ]);
});

it('flags a soft-deleted invoice number as taken', function (): void {
    $admin = invoiceManager();
    $client = ClientEloquentModel::factory()->active()->create(['client_name' => 'Archived Co']);
    $invoice = InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'client_id' => $client->id,
        'invoice_number' => '014/2026',
        'sequence' => 14,
        'year' => 2026,
    ]);
    $invoice->delete();

    $this->actingAs($admin)
        ->getJson('/data/admin/invoices/check-number?invoice_number=014/2026')
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('invoice.client_name', 'Archived Co')
        ->assertJsonPath('invoice.is_suspended', true);
});

it('rejects a duplicate invoice number', function (): void {
    $admin = invoiceManager();
    $client = ClientEloquentModel::factory()->active()->create();
    InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'client_id' => $client->id,
        'invoice_number' => '001/2026',
        'sequence' => 1,
        'year' => 2026,
    ]);

    $this->actingAs($admin)
        ->postJson('/data/admin/invoices', managementPayload($client))
        ->assertJsonValidationErrors('invoice_number');
});

it('recomputes totals and replaces the items on update', function (): void {
    $admin = invoiceManager();
    $client = ClientEloquentModel::factory()->active()->create();
    $invoice = InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'client_id' => $client->id,
        'invoice_number' => '003/2026',
        'sequence' => 3,
        'year' => 2026,
        'subtotal' => 10,
        'tax_amount' => 0,
        'total' => 10,
    ]);
    InvoiceItemEloquentModel::query()->create([
        'invoice_id' => $invoice->id,
        'sort_order' => 0,
        'title' => 'Old',
        'quantity' => 1,
        'unit_price' => 10,
        'amount' => 10,
    ]);

    $this->actingAs($admin)
        ->putJson("/data/admin/invoices/{$invoice->uuid}", managementPayload($client, [
            'invoice_number' => '003/2026',
            'items' => singleLine([
                'title' => 'New line',
                'description' => 'Updated',
                'quantity' => 2,
                'unit_price' => 40,
            ]),
        ]))
        ->assertOk();

    $invoice->refresh();

    expect($invoice->subtotal)->toBe('80.00')
        ->and($invoice->total)->toBe('80.00')
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->items->first()->title)->toBe('New line');
});

it('downloads the invoice as a pdf', function (): void {
    $admin = invoiceManager();
    $client = ClientEloquentModel::factory()->active()->create();
    $invoice = InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'client_id' => $client->id,
        'invoice_number' => '004/2026',
        'sequence' => 4,
        'year' => 2026,
    ]);
    InvoiceItemEloquentModel::query()->create([
        'invoice_id' => $invoice->id,
        'sort_order' => 0,
        'title' => 'Service',
        'quantity' => 1,
        'unit_price' => 100,
        'amount' => 100,
    ]);

    $response = $this->actingAs($admin)->get("/data/admin/invoices/{$invoice->uuid}/pdf")->assertOk();

    expect((string) $response->headers->get('content-type'))->toContain('application/pdf');
});

it('names the pdf download after the client, sequence and issue date', function (): void {
    $admin = invoiceManager();
    $client = ClientEloquentModel::factory()->active()->create([
        'client_name' => 'Aquashield Restoration LLC',
    ]);
    $invoice = InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'client_id' => $client->id,
        'invoice_number' => '015/2026',
        'sequence' => 15,
        'year' => 2026,
        'issue_date' => '2026-08-01',
    ]);
    InvoiceItemEloquentModel::query()->create([
        'invoice_id' => $invoice->id,
        'sort_order' => 0,
        'title' => 'Service',
        'quantity' => 1,
        'unit_price' => 100,
        'amount' => 100,
    ]);

    $this->actingAs($admin)
        ->get("/data/admin/invoices/{$invoice->uuid}/pdf")
        ->assertOk()
        ->assertHeader(
            'content-disposition',
            'attachment; filename="Invoice-Aquashield-Restoration-LLC-015-01-08-2026.pdf"',
        );
});

it('soft deletes and restores an invoice', function (): void {
    $admin = invoiceManager();
    $invoice = InvoiceEloquentModel::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->deleteJson("/data/admin/invoices/{$invoice->uuid}")->assertNoContent();
    $this->assertSoftDeleted('invoices', ['uuid' => $invoice->uuid]);

    $this->actingAs($admin)->patchJson("/data/admin/invoices/{$invoice->uuid}/restore")->assertOk();
    $this->assertDatabaseHas('invoices', ['uuid' => $invoice->uuid, 'deleted_at' => null]);
});

it('bulk deletes and bulk restores invoices', function (): void {
    Queue::fake();

    $admin = invoiceManager();
    $uuids = InvoiceEloquentModel::factory()->count(3)->create(['user_id' => $admin->id])->pluck('uuid')->all();

    $this->actingAs($admin)->postJson('/data/admin/invoices/bulk-delete', ['uuids' => $uuids])
        ->assertOk()
        ->assertJsonPath('deleted', 3);

    foreach ($uuids as $uuid) {
        $this->assertSoftDeleted('invoices', ['uuid' => $uuid]);
    }

    $this->actingAs($admin)->postJson('/data/admin/invoices/bulk-restore', ['uuids' => $uuids])
        ->assertOk()
        ->assertJsonPath('restored', 3);

    foreach ($uuids as $uuid) {
        $this->assertDatabaseHas('invoices', ['uuid' => $uuid, 'deleted_at' => null]);
    }

    // Restoring re-warms every restored PDF; deleting never renders one.
    Queue::assertPushed(GenerateInvoicePdfJob::class, 3);
});

it('links the service on a line item', function (): void {
    $admin = invoiceManager();
    $client = ClientEloquentModel::factory()->active()->create();
    $service = ServiceEloquentModel::factory()->create([
        'name' => 'Web Development',
        'description' => 'Remote web services',
        'is_active' => true,
        'user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->postJson('/data/admin/invoices', managementPayload($client, [
            'invoice_number' => '005/2026',
            'items' => singleLine([
                'title' => 'Web Development',
                'description' => 'Remote web services',
                'unit_price' => 200,
                'service_uuid' => $service->uuid,
            ]),
        ]))
        ->assertCreated();

    expect(InvoiceEloquentModel::query()->where('invoice_number', '005/2026')->firstOrFail()->items->first()->service_id)
        ->toBe($service->id);
});

it('links the primary product on the invoice header', function (): void {
    $admin = invoiceManager();
    $client = ClientEloquentModel::factory()->active()->create();
    $product = ProductEloquentModel::factory()->classroom()->create([
        'title' => 'Copilot Classroom',
        'price' => 1200,
        'user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->postJson('/data/admin/invoices', managementPayload($client, [
            'invoice_number' => '006/2026',
            'product_uuid' => $product->uuid,
            'items' => singleLine([
                'title' => 'Copilot Classroom',
                'description' => 'Classroom delivery',
                'unit_price' => 1200,
            ]),
        ]))
        ->assertCreated();

    expect(InvoiceEloquentModel::query()->where('invoice_number', '006/2026')->firstOrFail()->product_id)
        ->toBe($product->id);
});

it('forbids the user role from creating invoices', function (): void {
    $user = User::factory()->create();
    $user->assignRole('USER');
    $client = ClientEloquentModel::factory()->active()->create();

    $this->actingAs($user)
        ->postJson('/data/admin/invoices', managementPayload($client))
        ->assertForbidden();
});

it('lets the admin role open the invoices page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('ADMIN');

    $this->actingAs($admin)->get('/invoices')->assertOk();
});

it('renders the invoices page for an authorized user', function (): void {
    $this->actingAs(invoiceManager())->get('/invoices')->assertOk();
});

it('rejects a bulk delete of more than 500 uuids', function (): void {
    $uuids = array_map(static fn (): string => (string) Str::uuid7(), range(1, 501));

    $this->actingAs(invoiceManager())
        ->postJson('/data/admin/invoices/bulk-delete', ['uuids' => $uuids])
        ->assertStatus(422)
        ->assertJsonValidationErrors('uuids');
});

it('streams the invoices as csv', function (): void {
    $admin = invoiceManager();
    InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'invoice_number' => '099/2026',
        'sequence' => 99,
        'year' => 2026,
    ]);

    $this->actingAs($admin)->get('/data/admin/invoices/export?format=csv')->assertOk();
});

it('streams the invoices as a real xlsx workbook', function (): void {
    $admin = invoiceManager();
    InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'invoice_number' => '097/2026',
        'sequence' => 97,
        'year' => 2026,
    ]);

    $response = $this->actingAs($admin)->get('/data/admin/invoices/export?format=xlsx')->assertOk();

    // An XLSX is a zip container: the `PK` magic bytes prove the streamed
    // callback wrote a workbook rather than the CSV writer handling the request.
    expect((string) $response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($response->streamedContent())->toStartWith('PK');
});

it('renders the invoices report as a pdf', function (): void {
    $admin = invoiceManager();
    InvoiceEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'invoice_number' => '098/2026',
        'sequence' => 98,
        'year' => 2026,
    ]);

    $response = $this->actingAs($admin)->get('/data/admin/invoices/export?format=pdf')->assertOk();

    expect((string) $response->headers->get('content-type'))->toContain('application/pdf')
        ->and((string) $response->getContent())->toStartWith('%PDF');
});

it('rejects an unknown export format', function (): void {
    $this->actingAs(invoiceManager())
        ->get('/data/admin/invoices/export?format=docx')
        ->assertStatus(422);
});
