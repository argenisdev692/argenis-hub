<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

/**
 * `GET /data/admin/clients/export` — streams the filtered CRM list as
 * CSV / Excel / PDF through the Shared `ExportPort`. Reuses the SAME
 * `ClientEloquentModel::applyFilters()` as the admin list, so these tests lean
 * on the filter behaviour already covered in `ClientAdminCrudTest` and only
 * assert what export adds: the guard, the format switch, and that the active
 * filter reaches the stream.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  list<string>  $permissions
 */
function clientExportOperator(array $permissions = ['EXPORT_CLIENTS']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

describe('authorization', function (): void {
    it('turns a guest away', function (): void {
        $this->getJson(route('clients.admin.export'))->assertUnauthorized();
    });

    it('refuses a signed-in user without EXPORT_CLIENTS', function (): void {
        $this->actingAs(clientExportOperator(['VIEW_ANY_CLIENTS']))
            ->get(route('clients.admin.export'))
            ->assertForbidden();
    });
});

describe('formats', function (): void {
    it('defaults to an Excel download', function (): void {
        ClientEloquentModel::factory()->create();

        $response = $this->actingAs(clientExportOperator())
            ->get(route('clients.admin.export'));

        $response->assertOk()->assertDownload();
        expect($response->headers->get('content-type'))
            ->toContain('spreadsheetml.sheet');
    });

    it('streams a CSV with a header row and the client rows', function (): void {
        ClientEloquentModel::factory()->create(['client_name' => 'AQUASHIELD RESTORATION LLC']);

        $content = $this->actingAs(clientExportOperator())
            ->get(route('clients.admin.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        expect($content)
            ->toContain('Name', 'Email', 'Phone', 'Lifecycle', 'Owner', 'Created', 'Status')
            ->toContain('AQUASHIELD RESTORATION LLC');
    });

    it('renders a PDF', function (): void {
        ClientEloquentModel::factory()->create();

        $response = $this->actingAs(clientExportOperator())
            ->get(route('clients.admin.export', ['format' => 'pdf']))
            ->assertOk();

        expect($response->headers->get('content-type'))->toContain('application/pdf');
    });

    it('rejects an unknown format', function (): void {
        $this->actingAs(clientExportOperator())
            ->get(route('clients.admin.export', ['format' => 'json']))
            ->assertStatus(422);
    });
});

describe('respects the active filters', function (): void {
    it('applies the search term to the exported rows', function (): void {
        ClientEloquentModel::factory()->create(['client_name' => 'AQUASHIELD RESTORATION LLC']);
        ClientEloquentModel::factory()->create(['client_name' => 'CESAR AUGUSTO GONZALEZ']);

        $content = $this->actingAs(clientExportOperator())
            ->get(route('clients.admin.export', ['format' => 'csv', 'search' => 'aquashield']))
            ->streamedContent();

        expect($content)
            ->toContain('AQUASHIELD RESTORATION LLC')
            ->not->toContain('CESAR AUGUSTO GONZALEZ');
    });

    it('applies the status filter to the exported rows', function (): void {
        ClientEloquentModel::factory()->create(['client_name' => 'KEPT CLIENT LLC']);
        ClientEloquentModel::factory()->create(['client_name' => 'GONE CLIENT LLC'])->delete();

        $content = $this->actingAs(clientExportOperator())
            ->get(route('clients.admin.export', ['format' => 'csv', 'status' => 'deleted']))
            ->streamedContent();

        expect($content)
            ->toContain('GONE CLIENT LLC', 'Suspended')
            ->not->toContain('KEPT CLIENT LLC');
    });

    it('rejects an inverted date range', function (): void {
        $this->actingAs(clientExportOperator())
            ->getJson(route('clients.admin.export', [
                'format' => 'csv',
                'date_from' => '2026-08-20',
                'date_to' => '2026-08-10',
            ]))
            ->assertStatus(422);
    });
});
