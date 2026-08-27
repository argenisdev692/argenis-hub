<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

/**
 * `GET /data/admin/services/export` — streams the filtered catalog as
 * CSV / Excel / PDF through the Shared `ExportPort`. Reuses the SAME
 * `ServiceEloquentModel::applyFilters()` as the admin list, so these tests lean
 * on the filter behaviour already covered in `ServiceAdminCrudTest` and only
 * assert what export adds: the guard, the format switch, and that the active
 * filter reaches the stream.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  list<string>  $permissions
 */
function serviceExportOperator(array $permissions = ['EXPORT_SERVICES']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

describe('authorization', function (): void {
    it('turns a guest away', function (): void {
        $this->getJson(route('services.admin.export'))->assertUnauthorized();
    });

    it('refuses a signed-in user without EXPORT_SERVICES', function (): void {
        $this->actingAs(serviceExportOperator(['VIEW_ANY_SERVICES']))
            ->get(route('services.admin.export'))
            ->assertForbidden();
    });
});

describe('formats', function (): void {
    it('defaults to an Excel download', function (): void {
        ServiceEloquentModel::factory()->create();

        $response = $this->actingAs(serviceExportOperator())
            ->get(route('services.admin.export'));

        $response->assertOk()->assertDownload();
        expect($response->headers->get('content-type'))
            ->toContain('spreadsheetml.sheet');
    });

    it('streams a CSV with a header row and the catalog rows', function (): void {
        ServiceEloquentModel::factory()->create(['name' => 'Business Website', 'slug' => 'business_website']);

        $content = $this->actingAs(serviceExportOperator())
            ->get(route('services.admin.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        expect($content)
            ->toContain('Name', 'Slug', 'Description', 'Status', 'Order', 'Created')
            ->toContain('Business Website', 'business_website');
    });

    it('renders a PDF', function (): void {
        ServiceEloquentModel::factory()->create();

        $response = $this->actingAs(serviceExportOperator())
            ->get(route('services.admin.export', ['format' => 'pdf']))
            ->assertOk();

        expect($response->headers->get('content-type'))->toContain('application/pdf');
    });

    it('rejects an unknown format', function (): void {
        $this->actingAs(serviceExportOperator())
            ->get(route('services.admin.export', ['format' => 'json']))
            ->assertStatus(422);
    });
});

describe('respects the active filters', function (): void {
    it('applies the search term to the exported rows', function (): void {
        ServiceEloquentModel::factory()->create(['name' => 'Business Website', 'slug' => 'business_website']);
        ServiceEloquentModel::factory()->create(['name' => 'E-Commerce', 'slug' => 'ecommerce']);

        $content = $this->actingAs(serviceExportOperator())
            ->get(route('services.admin.export', ['format' => 'csv', 'search' => 'commerce']))
            ->streamedContent();

        expect($content)
            ->toContain('ecommerce')
            ->not->toContain('business_website');
    });

    it('applies the status filter to the exported rows', function (): void {
        ServiceEloquentModel::factory()->create(['slug' => 'kept']);
        ServiceEloquentModel::factory()->create(['slug' => 'gone'])->delete();

        $content = $this->actingAs(serviceExportOperator())
            ->get(route('services.admin.export', ['format' => 'csv', 'status' => 'deleted']))
            ->streamedContent();

        expect($content)
            ->toContain('gone', 'Deleted')
            ->not->toContain('kept');
    });

    it('rejects an inverted date range', function (): void {
        $this->actingAs(serviceExportOperator())
            ->getJson(route('services.admin.export', [
                'format' => 'csv',
                'date_from' => '2026-08-20',
                'date_to' => '2026-08-10',
            ]))
            ->assertStatus(422);
    });
});
