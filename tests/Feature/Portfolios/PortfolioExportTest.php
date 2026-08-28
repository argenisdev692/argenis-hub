<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

/**
 * `GET /data/admin/portfolios/export` — streams the filtered showcase as
 * CSV / Excel / PDF through the Shared `ExportPort`. Reuses the SAME
 * `PortfolioEloquentModel::applyFilters()` as the admin list, so these tests
 * lean on the filter behaviour already covered in `PortfolioAdminCrudTest` and
 * only assert what export adds: the guard, the format switch, and that the
 * active filter reaches the stream.
 */
beforeEach(function (): void {
    Storage::fake('r2');
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  list<string>  $permissions
 */
function portfolioExportOperator(array $permissions = ['EXPORT_PORTFOLIOS']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

describe('authorization', function (): void {
    it('turns a guest away', function (): void {
        $this->getJson(route('portfolios.admin.export'))->assertUnauthorized();
    });

    it('refuses a signed-in user without EXPORT_PORTFOLIOS', function (): void {
        $this->actingAs(portfolioExportOperator(['VIEW_ANY_PORTFOLIOS']))
            ->get(route('portfolios.admin.export'))
            ->assertForbidden();
    });
});

describe('formats', function (): void {
    it('defaults to an Excel download', function (): void {
        PortfolioEloquentModel::factory()->create();

        $response = $this->actingAs(portfolioExportOperator())
            ->get(route('portfolios.admin.export'));

        $response->assertOk()->assertDownload();
        expect($response->headers->get('content-type'))->toContain('spreadsheetml.sheet');
    });

    it('streams a CSV with a header row and the showcase rows', function (): void {
        PortfolioEloquentModel::factory()->create(['title' => 'Acme Rebrand', 'client_name' => 'Acme Inc.']);

        $content = $this->actingAs(portfolioExportOperator())
            ->get(route('portfolios.admin.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        expect($content)
            ->toContain('Title', 'Client', 'Type', 'Tech Stack', 'Public', 'Published', 'Owner', 'Created', 'Status')
            ->toContain('Acme Rebrand', 'Acme Inc.');
    });

    it('renders a PDF', function (): void {
        PortfolioEloquentModel::factory()->create();

        $response = $this->actingAs(portfolioExportOperator())
            ->get(route('portfolios.admin.export', ['format' => 'pdf']))
            ->assertOk();

        expect($response->headers->get('content-type'))->toContain('application/pdf');
    });

    it('rejects an unknown format', function (): void {
        $this->actingAs(portfolioExportOperator())
            ->get(route('portfolios.admin.export', ['format' => 'json']))
            ->assertStatus(422);
    });
});

describe('respects the active filters', function (): void {
    it('applies the search term to the exported rows', function (): void {
        PortfolioEloquentModel::factory()->create([
            'title' => 'Business Website', 'client_name' => 'Acme', 'project_type' => 'Web App',
        ]);
        PortfolioEloquentModel::factory()->create([
            'title' => 'Retail Store', 'client_name' => 'Commerce Partners', 'project_type' => 'Web App',
        ]);

        $content = $this->actingAs(portfolioExportOperator())
            ->get(route('portfolios.admin.export', ['format' => 'csv', 'search' => 'commerce']))
            ->streamedContent();

        expect($content)
            ->toContain('Retail Store')
            ->not->toContain('Business Website');
    });

    it('applies the status filter to the exported rows', function (): void {
        PortfolioEloquentModel::factory()->create(['title' => 'Kept Project']);
        PortfolioEloquentModel::factory()->create(['title' => 'Gone Project'])->delete();

        $content = $this->actingAs(portfolioExportOperator())
            ->get(route('portfolios.admin.export', ['format' => 'csv', 'status' => 'deleted']))
            ->streamedContent();

        expect($content)
            ->toContain('Gone Project', 'Suspended')
            ->not->toContain('Kept Project');
    });

    it('rejects an inverted date range', function (): void {
        $this->actingAs(portfolioExportOperator())
            ->getJson(route('portfolios.admin.export', [
                'format' => 'csv',
                'date_from' => '2026-08-20',
                'date_to' => '2026-08-10',
            ]))
            ->assertStatus(422);
    });
});
