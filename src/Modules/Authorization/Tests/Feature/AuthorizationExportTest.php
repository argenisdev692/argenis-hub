<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function exportOperator(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function streamedBody(Response $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('streams the role list as CSV with the mandated column set', function (): void {
    Role::factory()->create(['name' => 'EXPORTABLE_ROLE']);

    $response = $this->actingAs(exportOperator(['EXPORT_ROLES']))
        ->get('/roles/export?format=csv')
        ->assertOk();

    $body = streamedBody($response->baseResponse);

    expect($body)->toContain('Name', 'Guard', 'Permissions', 'Created', 'Status')
        ->and($body)->toContain('EXPORTABLE_ROLE');
});

it('formats export dates as "F j, Y" rather than ISO/date-time', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-03 09:30:00'));
    Role::factory()->create(['name' => 'DATED_ROLE']);
    Carbon::setTestNow();

    $response = $this->actingAs(exportOperator(['EXPORT_ROLES']))
        ->get('/roles/export?format=csv')
        ->assertOk();

    $body = streamedBody($response->baseResponse);

    expect($body)->toContain('March 3, 2026')
        ->and($body)->not->toContain('2026-03-03 09:30:00');
});

it('labels soft-deleted rows Suspended, never Inactive', function (): void {
    Role::factory()->suspended()->create(['name' => 'SUSPENDED_ROLE']);

    $response = $this->actingAs(exportOperator(['EXPORT_ROLES']))
        ->get('/roles/export?format=csv&status=suspended')
        ->assertOk();

    $body = streamedBody($response->baseResponse);

    expect($body)->toContain('SUSPENDED_ROLE', 'Suspended')
        ->and($body)->not->toContain('Inactive');
});

it('renders the role list as a PDF', function (): void {
    Role::factory()->create(['name' => 'PDF_ROLE']);

    $this->actingAs(exportOperator(['EXPORT_ROLES']))
        ->get('/roles/export?format=pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('streams the permission list as CSV', function (): void {
    Permission::factory()->create(['name' => 'EXPORTABLE_PERMISSION']);

    $response = $this->actingAs(exportOperator(['EXPORT_PERMISSIONS']))
        ->get('/permissions/export?format=csv')
        ->assertOk();

    expect(streamedBody($response->baseResponse))->toContain('EXPORTABLE_PERMISSION', 'Roles', 'Status');
});

it('rejects an unknown export format', function (): void {
    $this->actingAs(exportOperator(['EXPORT_ROLES']))
        ->get('/roles/export?format=docx')
        ->assertStatus(422);
});

it('forbids exporting without the EXPORT permission', function (): void {
    $this->actingAs(exportOperator(['VIEW_ANY_ROLES']))
        ->get('/roles/export?format=csv')
        ->assertForbidden();

    $this->actingAs(exportOperator(['VIEW_ANY_PERMISSIONS']))
        ->get('/permissions/export?format=csv')
        ->assertForbidden();
});
