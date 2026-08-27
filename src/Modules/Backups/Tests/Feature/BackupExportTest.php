<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;

/**
 * `GET /data/admin/backups/export` — streams the filtered index as CSV / Excel /
 * PDF through the Shared `ExportPort`, reusing the SAME
 * `BackupEloquentModel::applyFilters()` as the admin list. These tests assert
 * only what export adds: the guard, the format switch, and that a filter reaches
 * the stream.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  list<string>  $permissions
 */
function backupExportOperator(array $permissions = ['EXPORT_BACKUPS']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

describe('authorization', function (): void {
    it('turns a guest away', function (): void {
        $this->getJson(route('backups.admin.export'))->assertUnauthorized();
    });

    it('refuses a signed-in user without EXPORT_BACKUPS', function (): void {
        $this->actingAs(backupExportOperator(['VIEW_ANY_BACKUPS']))
            ->get(route('backups.admin.export'))
            ->assertForbidden();
    });
});

describe('formats', function (): void {
    it('defaults to an Excel download', function (): void {
        BackupEloquentModel::factory()->create();

        $response = $this->actingAs(backupExportOperator())
            ->get(route('backups.admin.export'));

        $response->assertOk()->assertDownload();
        expect($response->headers->get('content-type'))->toContain('spreadsheetml.sheet');
    });

    it('streams a CSV with the header row and the archive rows', function (): void {
        BackupEloquentModel::factory()->create(['filename' => 'nightly-db.zip', 'connection' => 'mysql']);

        $content = $this->actingAs(backupExportOperator())
            ->get(route('backups.admin.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        expect($content)
            ->toContain('Filename', 'Disk', 'Size', 'Status', 'Connection', 'Started', 'Finished', 'Created')
            ->toContain('nightly-db.zip', 'Completed');
    });

    it('renders a PDF', function (): void {
        BackupEloquentModel::factory()->create();

        $response = $this->actingAs(backupExportOperator())
            ->get(route('backups.admin.export', ['format' => 'pdf']))
            ->assertOk();

        expect($response->headers->get('content-type'))->toContain('application/pdf');
    });

    it('rejects an unknown format', function (): void {
        $this->actingAs(backupExportOperator())
            ->get(route('backups.admin.export', ['format' => 'json']))
            ->assertStatus(422);
    });
});

describe('respects the active filters', function (): void {
    it('applies the status filter to the exported rows', function (): void {
        BackupEloquentModel::factory()->create(['filename' => 'ok-db.zip']);
        BackupEloquentModel::factory()->failed()->create(['filename' => 'bad-db.zip']);

        $content = $this->actingAs(backupExportOperator())
            ->get(route('backups.admin.export', ['format' => 'csv', 'status' => 'failed']))
            ->streamedContent();

        expect($content)
            ->toContain('bad-db.zip', 'Failed')
            ->not->toContain('ok-db.zip');
    });

    it('rejects an inverted date range', function (): void {
        $this->actingAs(backupExportOperator())
            ->getJson(route('backups.admin.export', [
                'format' => 'csv',
                'date_from' => '2026-08-20',
                'date_to' => '2026-08-10',
            ]))
            ->assertStatus(422);
    });
});
