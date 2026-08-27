<?php

declare(strict_types=1);

use App\Models\CompanyData;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Shared\Infrastructure\Company\CompanyProfile;
use Spatie\Activitylog\Models\Activity;

/**
 * The reversible soft delete on the singleton company record.
 *
 * DELETE trashes the row and bounces the operator to the dashboard; while it is
 * trashed the settings screen becomes the "deleted, restore it" surface and
 * every branding consumer falls back to the app defaults; PATCH /restore brings
 * it back. Both actions are SUPER_ADMIN-only, guarded by DELETE_COMPANY_DATA /
 * RESTORE_COMPANY_DATA.
 */
beforeEach(function (): void {
    CompanyProfile::forget();
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/**
 * @param  list<string>  $permissions
 */
function deleteOperator(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

it('lets an operator with DELETE_COMPANY_DATA soft-delete the company and redirects to the dashboard', function (): void {
    $company = CompanyData::factory()->create();

    $this->actingAs(deleteOperator(['VIEW_COMPANY_DATA', 'DELETE_COMPANY_DATA']))
        ->delete(route('company.destroy'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('status', 'company-deleted');

    $this->assertSoftDeleted('company_data', ['id' => $company->id]);
});

it('reverts branding to the app defaults while the company is deleted', function (): void {
    CompanyData::factory()->create(['company_name' => 'Acme Iberia']);

    $this->actingAs(deleteOperator(['DELETE_COMPANY_DATA']))
        ->delete(route('company.destroy'))
        ->assertRedirect(route('dashboard'));

    CompanyProfile::forget();

    expect(CompanyProfile::data()['name'])->toBe(config('app.name'));
});

it('records the deletion in the activity log under company.data', function (): void {
    CompanyData::factory()->create();

    $this->actingAs(deleteOperator(['DELETE_COMPANY_DATA']))
        ->delete(route('company.destroy'))
        ->assertRedirect(route('dashboard'));

    expect(
        Activity::query()
            ->where('log_name', 'company.data')
            ->where('event', 'deleted')
            ->where('subject_type', CompanyData::class)
            ->count()
    )->toBe(1);
});

it('forbids deleting the company without DELETE_COMPANY_DATA', function (): void {
    $company = CompanyData::factory()->create();

    $this->actingAs(deleteOperator(['VIEW_COMPANY_DATA', 'UPDATE_COMPANY_DATA']))
        ->delete(route('company.destroy'))
        ->assertForbidden();

    $this->assertNotSoftDeleted('company_data', ['id' => $company->id]);
});

it('redirects guests away from the delete endpoint', function (): void {
    CompanyData::factory()->create();

    $this->delete(route('company.destroy'))->assertRedirect(route('login'));
});

it('shows the deleted screen at /settings/company when the record is trashed', function (): void {
    CompanyData::factory()->create()->delete();

    $this->actingAs(deleteOperator(['VIEW_COMPANY_DATA', 'RESTORE_COMPANY_DATA']))
        ->get(route('company.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/company/Deleted'));
});

it('still 404s at /settings/company when the company was never created', function (): void {
    $this->actingAs(deleteOperator(['VIEW_COMPANY_DATA', 'RESTORE_COMPANY_DATA']))
        ->get(route('company.show'))
        ->assertNotFound();
});

it('lets an operator with RESTORE_COMPANY_DATA restore a trashed company and redirects to settings', function (): void {
    $company = CompanyData::factory()->create();
    $company->delete();

    $this->actingAs(deleteOperator(['VIEW_COMPANY_DATA', 'RESTORE_COMPANY_DATA']))
        ->patch(route('company.restore'))
        ->assertRedirect(route('company.show'))
        ->assertSessionHas('status', 'company-restored');

    expect($company->fresh()->trashed())->toBeFalse();
});

it('forbids restoring without RESTORE_COMPANY_DATA', function (): void {
    $company = CompanyData::factory()->create();
    $company->delete();

    $this->actingAs(deleteOperator(['VIEW_COMPANY_DATA', 'UPDATE_COMPANY_DATA']))
        ->patch(route('company.restore'))
        ->assertForbidden();

    expect($company->fresh()->trashed())->toBeTrue();
});

it('404s on restore when there is no trashed company', function (): void {
    CompanyData::factory()->create();

    $this->actingAs(deleteOperator(['RESTORE_COMPANY_DATA']))
        ->patch(route('company.restore'))
        ->assertNotFound();
});

it('grants SUPER_ADMIN the new delete and restore permissions', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('SUPER_ADMIN');

    expect($superAdmin->can('DELETE_COMPANY_DATA'))->toBeTrue()
        ->and($superAdmin->can('RESTORE_COMPANY_DATA'))->toBeTrue();
});
