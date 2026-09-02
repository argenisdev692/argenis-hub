<?php

declare(strict_types=1);

namespace Modules\Invoices\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Application\Support\InvoiceClientBilling;
use Modules\Invoices\Application\Support\InvoicePdfViewAssembler;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceItemEloquentModel;
use Modules\Invoices\Infrastructure\Queue\GenerateInvoicePdfJob;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;
use Tests\TestCase;

final class InvoiceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        return $admin;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(ClientEloquentModel $client, array $overrides = []): array
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

    public function test_super_admin_creates_invoice_with_computed_totals(): void
    {
        Queue::fake();

        $admin = $this->superAdmin();
        $client = ClientEloquentModel::factory()->active()->create([
            'client_name' => 'AquaShield',
            'tax_id' => '36-5164436',
        ]);

        $this->actingAs($admin)
            ->postJson('/data/admin/invoices', $this->validPayload($client))
            ->assertCreated();

        $invoice = InvoiceEloquentModel::query()->where('invoice_number', '001/2026')->firstOrFail();

        $this->assertSame($admin->id, $invoice->user_id);
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame(1, $invoice->sequence);
        $this->assertSame(2026, $invoice->year);
        $this->assertSame('EXEMPT', $invoice->tax_mode);
        $this->assertSame('200.00', $invoice->subtotal);
        $this->assertSame('0.00', $invoice->tax_amount);
        $this->assertSame('200.00', $invoice->total);
        $this->assertSame('USD', $invoice->currency);
        $this->assertCount(2, $invoice->items);

        Queue::assertPushed(GenerateInvoicePdfJob::class);
    }

    public function test_create_persists_eur_currency_when_requested(): void
    {
        Queue::fake();

        $admin = $this->superAdmin();
        $client = ClientEloquentModel::factory()->active()->create([
            'country_code' => 'ES',
        ]);

        $this->actingAs($admin)
            ->postJson('/data/admin/invoices', $this->validPayload($client, [
                'invoice_number' => '020/2026',
                'currency' => 'EUR',
            ]))
            ->assertCreated();

        $invoice = InvoiceEloquentModel::query()->where('invoice_number', '020/2026')->firstOrFail();
        $this->assertSame('EUR', $invoice->currency);
    }

    public function test_update_changes_currency_and_pdf_assembler_symbol(): void
    {
        $admin = $this->superAdmin();
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
            ->putJson("/data/admin/invoices/{$invoice->uuid}", $this->validPayload($client, [
                'invoice_number' => '021/2026',
                'currency' => 'USD',
            ]))
            ->assertOk();

        $invoice->refresh()->load('client');
        $this->assertSame('USD', $invoice->currency);

        $assembler = new InvoicePdfViewAssembler;
        $pdf = $assembler->assemble($invoice, ['country' => 'Portugal', 'country_code' => 'PT']);
        $this->assertSame('$', $pdf['currency_symbol']);
    }

    public function test_show_json_includes_items_and_currency_for_edit_hydrate(): void
    {
        $admin = $this->superAdmin();
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
            ->getJson("/data/admin/invoices/{$invoice->uuid}");

        $response->assertOk()
            ->assertJsonPath('currency', 'USD')
            ->assertJsonPath('notes', 'Stored fiscal notice')
            ->assertJsonPath('additional_notes', 'Extra note')
            ->assertJsonPath('items.0.title', 'Consulting');

        $this->assertSame(2.0, (float) $response->json('items.0.quantity'));
        $this->assertSame(50.0, (float) $response->json('items.0.unit_price'));
    }

    public function test_create_links_client_for_pdf_and_listing(): void
    {
        Queue::fake();

        $admin = $this->superAdmin();
        $client = ClientEloquentModel::factory()->active()->create([
            'country' => 'Spain',
            'country_code' => 'ES',
            'email' => 'billing@client.test',
            'phone' => '+34600111222',
            'nif' => 'B98330335',
            'tax_id' => '0',
        ]);

        $this->actingAs($admin)
            ->postJson('/data/admin/invoices', $this->validPayload($client, ['invoice_number' => '002/2026']))
            ->assertCreated();

        $invoice = InvoiceEloquentModel::query()
            ->with('client:id,uuid,client_name,email,phone,tax_id,nif,country,country_code')
            ->where('invoice_number', '002/2026')
            ->firstOrFail();

        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame('ES', $invoice->client?->country_code);
        $this->assertSame('billing@client.test', $invoice->client?->email);
        $this->assertSame('B98330335', InvoiceClientBilling::taxIdForClient($invoice->client));
    }

    public function test_zero_percent_tax_keeps_total_equal_to_subtotal(): void
    {
        $admin = $this->superAdmin();
        $client = ClientEloquentModel::factory()->active()->create();

        $this->actingAs($admin)
            ->postJson('/data/admin/invoices', $this->validPayload($client, [
                'invoice_number' => '010/2026',
                'tax_mode' => 'PERCENT',
                'tax_rate' => 0,
                'items' => [
                    [
                        'title' => 'Consulting',
                        'description' => null,
                        'quantity' => 1,
                        'unit_price' => 100,
                        'service_uuid' => null,
                        'sort_order' => 0,
                    ],
                ],
            ]))
            ->assertCreated();

        $invoice = InvoiceEloquentModel::query()->where('invoice_number', '010/2026')->firstOrFail();
        $this->assertSame('PERCENT', $invoice->tax_mode);
        $this->assertSame('0.0000', $invoice->tax_rate);
        $this->assertSame('100.00', $invoice->subtotal);
        $this->assertSame('0.00', $invoice->tax_amount);
        $this->assertSame('100.00', $invoice->total);
    }

    public function test_paid_invoice_stores_payment_details(): void
    {
        $admin = $this->superAdmin();
        $client = ClientEloquentModel::factory()->active()->create();

        $this->actingAs($admin)
            ->postJson('/data/admin/invoices', $this->validPayload($client, [
                'invoice_number' => '011/2026',
                'is_paid' => true,
                'payment_method' => PaymentMethod::Remitly->value,
                'transfer_number' => 'R20 386 959 937',
                'payment_date' => '2026-05-02',
                'amount_received' => 25,
                'items' => [
                    [
                        'title' => 'Fix',
                        'description' => null,
                        'quantity' => 1,
                        'unit_price' => 25,
                        'service_uuid' => null,
                        'sort_order' => 0,
                    ],
                ],
            ]))
            ->assertCreated();

        $invoice = InvoiceEloquentModel::query()->where('invoice_number', '011/2026')->firstOrFail();
        $this->assertTrue($invoice->is_paid);
        $this->assertSame(PaymentMethod::Remitly, $invoice->payment_method);
        $this->assertSame('R20 386 959 937', $invoice->transfer_number);
        $this->assertSame('2026-05-02', $invoice->payment_date?->toDateString());
        $this->assertSame('25.00', $invoice->amount_received);
    }

    public function test_paid_invoice_requires_payment_fields(): void
    {
        $admin = $this->superAdmin();
        $client = ClientEloquentModel::factory()->active()->create();

        $this->actingAs($admin)
            ->postJson('/data/admin/invoices', $this->validPayload($client, [
                'invoice_number' => '012/2026',
                'is_paid' => true,
                'payment_method' => null,
                'payment_date' => null,
                'amount_received' => null,
            ]))
            ->assertJsonValidationErrors(['payment_method', 'payment_date', 'amount_received']);
    }

    public function test_percent_tax_is_applied_to_total(): void
    {
        $admin = $this->superAdmin();
        $client = ClientEloquentModel::factory()->active()->create();

        $this->actingAs($admin)
            ->postJson('/data/admin/invoices', $this->validPayload($client, [
                'invoice_number' => '002/2026',
                'tax_mode' => 'PERCENT',
                'tax_rate' => 23,
                'items' => [
                    [
                        'title' => 'Consulting',
                        'description' => null,
                        'quantity' => 1,
                        'unit_price' => 100,
                        'service_uuid' => null,
                        'sort_order' => 0,
                    ],
                ],
            ]))
            ->assertCreated();

        $invoice = InvoiceEloquentModel::query()->where('invoice_number', '002/2026')->firstOrFail();
        $this->assertSame('100.00', $invoice->subtotal);
        $this->assertSame('23.00', $invoice->tax_amount);
        $this->assertSame('123.00', $invoice->total);
    }

    public function test_next_invoice_number_suggests_sequence_after_last(): void
    {
        $admin = $this->superAdmin();
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
    }

    public function test_check_number_reports_available_when_free(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->getJson('/data/admin/invoices/check-number?invoice_number=014/2026')
            ->assertOk()
            ->assertJson([
                'available' => true,
                'invoice_number' => '014/2026',
                'invoice' => null,
            ]);
    }

    public function test_check_number_returns_client_name_when_taken(): void
    {
        $admin = $this->superAdmin();
        $client = ClientEloquentModel::factory()->active()->create([
            'client_name' => 'Aquashield Restoration LLC',
        ]);
        InvoiceEloquentModel::factory()->create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'invoice_number' => '014/2026',
            'sequence' => 14,
            'year' => 2026,
        ]);

        $this->actingAs($admin)
            ->getJson('/data/admin/invoices/check-number?invoice_number=014')
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('invoice_number', '014/'.now()->year)
            ->assertJsonPath('invoice.client_name', 'Aquashield Restoration LLC')
            ->assertJsonPath('invoice.is_suspended', false);
    }

    public function test_check_number_ignores_current_invoice_on_edit(): void
    {
        $admin = $this->superAdmin();
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
    }

    public function test_check_number_flags_soft_deleted_as_taken(): void
    {
        $admin = $this->superAdmin();
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
    }

    public function test_duplicate_invoice_number_is_rejected(): void
    {
        $admin = $this->superAdmin();
        $client = ClientEloquentModel::factory()->active()->create();
        InvoiceEloquentModel::factory()->create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'invoice_number' => '001/2026',
            'sequence' => 1,
            'year' => 2026,
        ]);

        $this->actingAs($admin)
            ->postJson('/data/admin/invoices', $this->validPayload($client))
            ->assertJsonValidationErrors('invoice_number');
    }

    public function test_update_recomputes_totals_and_replaces_items(): void
    {
        $admin = $this->superAdmin();
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
            ->putJson("/data/admin/invoices/{$invoice->uuid}", $this->validPayload($client, [
                'invoice_number' => '003/2026',
                'items' => [
                    [
                        'title' => 'New line',
                        'description' => 'Updated',
                        'quantity' => 2,
                        'unit_price' => 40,
                        'service_uuid' => null,
                        'sort_order' => 0,
                    ],
                ],
            ]))
            ->assertOk();

        $invoice->refresh();
        $this->assertSame('80.00', $invoice->subtotal);
        $this->assertSame('80.00', $invoice->total);
        $this->assertCount(1, $invoice->items);
        $this->assertSame('New line', $invoice->items->first()->title);
    }

    public function test_pdf_download_returns_pdf_response(): void
    {
        $admin = $this->superAdmin();
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

        $response = $this->actingAs($admin)
            ->get("/data/admin/invoices/{$invoice->uuid}/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_pdf_download_filename_includes_client_sequence_and_issue_date(): void
    {
        $admin = $this->superAdmin();
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
    }

    public function test_delete_then_restore_invoice(): void
    {
        $admin = $this->superAdmin();
        $invoice = InvoiceEloquentModel::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin)->deleteJson("/data/admin/invoices/{$invoice->uuid}")->assertNoContent();
        $this->assertSoftDeleted('invoices', ['uuid' => $invoice->uuid]);

        $this->actingAs($admin)->patchJson("/data/admin/invoices/{$invoice->uuid}/restore")->assertOk();
        $this->assertDatabaseHas('invoices', ['uuid' => $invoice->uuid, 'deleted_at' => null]);
    }

    public function test_bulk_delete_then_restore(): void
    {
        $admin = $this->superAdmin();
        $uuids = InvoiceEloquentModel::factory()->count(3)->create(['user_id' => $admin->id])->pluck('uuid')->all();

        $this->actingAs($admin)->postJson('/data/admin/invoices/bulk-delete', ['uuids' => $uuids])->assertOk();
        foreach ($uuids as $uuid) {
            $this->assertSoftDeleted('invoices', ['uuid' => $uuid]);
        }

        $this->actingAs($admin)->postJson('/data/admin/invoices/bulk-restore', ['uuids' => $uuids])->assertOk();
        foreach ($uuids as $uuid) {
            $this->assertDatabaseHas('invoices', ['uuid' => $uuid, 'deleted_at' => null]);
        }
    }

    public function test_service_uuid_is_linked_on_line_item(): void
    {
        $admin = $this->superAdmin();
        $client = ClientEloquentModel::factory()->active()->create();
        $service = ServiceEloquentModel::factory()->create([
            'name' => 'Web Development',
            'description' => 'Remote web services',
            'is_active' => true,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->postJson('/data/admin/invoices', $this->validPayload($client, [
                'invoice_number' => '005/2026',
                'items' => [
                    [
                        'title' => 'Web Development',
                        'description' => 'Remote web services',
                        'quantity' => 1,
                        'unit_price' => 200,
                        'service_uuid' => $service->uuid,
                        'sort_order' => 0,
                    ],
                ],
            ]))
            ->assertCreated();

        $invoice = InvoiceEloquentModel::query()->where('invoice_number', '005/2026')->firstOrFail();
        $this->assertSame($service->id, $invoice->items->first()->service_id);
    }

    public function test_product_uuid_is_linked_on_invoice(): void
    {
        $admin = $this->superAdmin();
        $client = ClientEloquentModel::factory()->active()->create();
        $product = ProductEloquentModel::factory()
            ->classroom()
            ->create([
                'title' => 'Copilot Classroom',
                'price' => 1200,
                'user_id' => $admin->id,
            ]);

        $this->actingAs($admin)
            ->postJson('/data/admin/invoices', $this->validPayload($client, [
                'invoice_number' => '006/2026',
                'product_uuid' => $product->uuid,
                'items' => [
                    [
                        'title' => 'Copilot Classroom',
                        'description' => 'Classroom delivery',
                        'quantity' => 1,
                        'unit_price' => 1200,
                        'service_uuid' => null,
                        'sort_order' => 0,
                    ],
                ],
            ]))
            ->assertCreated();

        $invoice = InvoiceEloquentModel::query()->where('invoice_number', '006/2026')->firstOrFail();
        $this->assertSame($product->id, $invoice->product_id);
    }

    public function test_user_role_cannot_create_invoices(): void
    {
        $user = User::factory()->create();
        $user->assignRole('USER');
        $client = ClientEloquentModel::factory()->active()->create();

        $this->actingAs($user)
            ->postJson('/data/admin/invoices', $this->validPayload($client))
            ->assertForbidden();
    }

    public function test_admin_role_can_view_invoices_index(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('ADMIN');

        $this->actingAs($admin)->get('/invoices')->assertOk();
    }

    public function test_index_page_renders_for_authorized_user(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/invoices')
            ->assertOk();
    }

    public function test_bulk_delete_rejects_more_than_500_uuids(): void
    {
        $uuids = array_map(static fn (): string => (string) Str::uuid(), range(1, 501));

        $this->actingAs($this->superAdmin())
            ->postJson('/data/admin/invoices/bulk-delete', ['uuids' => $uuids])
            ->assertStatus(422)
            ->assertJsonValidationErrors('uuids');
    }

    public function test_export_csv_streams_successfully(): void
    {
        $admin = $this->superAdmin();
        InvoiceEloquentModel::factory()->create([
            'user_id' => $admin->id,
            'invoice_number' => '099/2026',
            'sequence' => 99,
            'year' => 2026,
        ]);

        $this->actingAs($admin)
            ->get('/data/admin/invoices/export?format=csv')
            ->assertOk();
    }

    public function test_export_rejects_invalid_format(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/data/admin/invoices/export?format=docx')
            ->assertStatus(422);
    }
}
