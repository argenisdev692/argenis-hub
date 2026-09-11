<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function invoiceApiAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('lists invoices over sanctum', function (): void {
    Sanctum::actingAs(invoiceApiAdmin());
    $client = ClientEloquentModel::factory()->active()->create(['client_name' => 'API Invoice Client']);
    InvoiceEloquentModel::factory()->create([
        'client_id' => $client->id,
        'invoice_number' => '050/2026',
        'sequence' => 50,
        'year' => 2026,
    ]);

    $this->getJson('/api/invoices')
        ->assertOk()
        ->assertJsonFragment(['invoice_number' => '050/2026']);
});

it('shows an invoice by uuid over sanctum', function (): void {
    Sanctum::actingAs(invoiceApiAdmin());
    $client = ClientEloquentModel::factory()->active()->create(['client_name' => 'Shown Invoice Client']);
    $invoice = InvoiceEloquentModel::factory()->create([
        'client_id' => $client->id,
        'invoice_number' => '051/2026',
        'sequence' => 51,
        'year' => 2026,
    ]);

    $this->getJson("/api/invoices/{$invoice->uuid}")
        ->assertOk()
        ->assertJsonPath('data.invoice_number', '051/2026');
});

it('rejects an unauthenticated api request', function (): void {
    $this->getJson('/api/invoices')->assertUnauthorized();
});

it('forbids the user role from listing invoices over the api', function (): void {
    $user = User::factory()->create();
    $user->assignRole('USER');
    Sanctum::actingAs($user);

    $this->getJson('/api/invoices')->assertForbidden();
});
