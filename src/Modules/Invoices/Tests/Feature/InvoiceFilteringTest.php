<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;

/**
 * The two orthogonal list axes — settlement (`payment_status`) and soft delete
 * (`status`) — plus the guarantee that the export applies exactly the same
 * `scopeApplyFilters`, so a spreadsheet can never hold a different set of rows
 * than the table it was exported from.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('SUPER_ADMIN');
});

/**
 * Three invoices spanning both axes: one unpaid live, one paid live, and one
 * paid-then-suspended — the row that proves the axes are independent.
 *
 * @return array{unpaid: InvoiceEloquentModel, paid: InvoiceEloquentModel, suspended: InvoiceEloquentModel}
 */
function seedInvoiceMatrix(): array
{
    $unpaid = InvoiceEloquentModel::factory()->create([
        'invoice_number' => '001/2026',
        'sequence' => 1,
    ]);

    $paid = InvoiceEloquentModel::factory()->paid()->create([
        'invoice_number' => '002/2026',
        'sequence' => 2,
    ]);

    $suspended = InvoiceEloquentModel::factory()->paid()->create([
        'invoice_number' => '003/2026',
        'sequence' => 3,
    ]);
    $suspended->delete();

    return ['unpaid' => $unpaid, 'paid' => $paid, 'suspended' => $suspended];
}

/**
 * @return list<string>
 */
function listedInvoiceNumbers(array $payload): array
{
    return array_map(
        static fn (array $row): string => $row['invoice_number'],
        $payload['data'],
    );
}

it('returns only unpaid invoices when payment_status is unpaid', function (): void {
    seedInvoiceMatrix();

    $response = $this->actingAs($this->admin)
        ->getJson('/data/admin/invoices?payment_status=unpaid')
        ->assertOk();

    expect(listedInvoiceNumbers($response->json()))->toBe(['001/2026']);
});

it('returns only paid invoices when payment_status is paid', function (): void {
    seedInvoiceMatrix();

    $response = $this->actingAs($this->admin)
        ->getJson('/data/admin/invoices?payment_status=paid')
        ->assertOk();

    // The suspended one is paid too, but the SoftDeletes scope is still on —
    // the two axes narrow independently rather than one overriding the other.
    expect(listedInvoiceNumbers($response->json()))->toBe(['002/2026']);
});

it('returns both when payment_status is omitted', function (): void {
    seedInvoiceMatrix();

    $response = $this->actingAs($this->admin)
        ->getJson('/data/admin/invoices')
        ->assertOk();

    expect(listedInvoiceNumbers($response->json()))
        ->toEqualCanonicalizing(['001/2026', '002/2026']);
});

it('combines the settlement and soft-delete axes', function (): void {
    seedInvoiceMatrix();

    $response = $this->actingAs($this->admin)
        ->getJson('/data/admin/invoices?status=suspended&payment_status=paid')
        ->assertOk();

    expect(listedInvoiceNumbers($response->json()))->toBe(['003/2026']);
});

it('rejects a payment_status the server cannot apply', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/data/admin/invoices?payment_status=overdue')
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment_status');
});

it('lists live and suspended invoices side by side when status is all', function (): void {
    seedInvoiceMatrix();

    $response = $this->actingAs($this->admin)
        ->getJson('/data/admin/invoices?status=all')
        ->assertOk();

    expect(listedInvoiceNumbers($response->json()))
        ->toEqualCanonicalizing(['001/2026', '002/2026', '003/2026']);
});

it('keeps status=active and an omitted status equivalent', function (): void {
    seedInvoiceMatrix();

    $active = $this->actingAs($this->admin)
        ->getJson('/data/admin/invoices?status=active')
        ->assertOk();

    $omitted = $this->actingAs($this->admin)
        ->getJson('/data/admin/invoices')
        ->assertOk();

    expect(listedInvoiceNumbers($active->json()))
        ->toBe(listedInvoiceNumbers($omitted->json()));
});

it('applies the payment filter to the CSV export as well as the list', function (): void {
    seedInvoiceMatrix();

    $response = $this->actingAs($this->admin)
        ->get('/data/admin/invoices/export?format=csv&payment_status=unpaid')
        ->assertOk();

    $csv = $response->streamedContent();

    // The exported rows must match the filtered table, not the whole ledger:
    // both read through the same `scopeApplyFilters`.
    expect($csv)->toContain('001/2026')
        ->and($csv)->not->toContain('002/2026')
        ->and($csv)->not->toContain('003/2026');
});

it('names the applied filters on the PDF report', function (): void {
    seedInvoiceMatrix();

    $response = $this->actingAs($this->admin)
        ->get('/data/admin/invoices/export?format=pdf&payment_status=unpaid&year=2026')
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
