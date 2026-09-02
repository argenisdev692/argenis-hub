<?php

declare(strict_types=1);

namespace Modules\Invoices\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Tests\TestCase;

final class InvoiceApiTest extends TestCase
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

    public function test_sanctum_lists_invoices(): void
    {
        Sanctum::actingAs($this->superAdmin());
        $client = ClientEloquentModel::factory()->active()->create([
            'client_name' => 'API Invoice Client',
        ]);
        InvoiceEloquentModel::factory()->create([
            'client_id' => $client->id,
            'invoice_number' => '050/2026',
            'sequence' => 50,
            'year' => 2026,
        ]);

        $this->getJson('/api/invoices')
            ->assertOk()
            ->assertJsonFragment(['invoice_number' => '050/2026']);
    }

    public function test_sanctum_shows_an_invoice_by_uuid(): void
    {
        Sanctum::actingAs($this->superAdmin());
        $client = ClientEloquentModel::factory()->active()->create([
            'client_name' => 'Shown Invoice Client',
        ]);
        $invoice = InvoiceEloquentModel::factory()->create([
            'client_id' => $client->id,
            'invoice_number' => '051/2026',
            'sequence' => 51,
            'year' => 2026,
        ]);

        $this->getJson("/api/invoices/{$invoice->uuid}")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', '051/2026');
    }

    public function test_unauthenticated_api_is_rejected(): void
    {
        $this->getJson('/api/invoices')->assertUnauthorized();
    }

    public function test_user_role_cannot_list_invoices_via_api(): void
    {
        $user = User::factory()->create();
        $user->assignRole('USER');
        Sanctum::actingAs($user);

        $this->getJson('/api/invoices')->assertForbidden();
    }
}
