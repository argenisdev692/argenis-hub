<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;

/**
 * `/data/admin/backups` — the JSON surface the backups panel consumes. Every
 * route is guarded by its own `*_BACKUPS` permission (seeded in
 * `RolePermissionSeeder::BACKUP_ACTIONS`). Backups are database-only, immutable
 * archives: no create/update of a row from the UI, and delete is a HARD delete
 * of the archive file plus its index row.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  list<string>  $permissions
 */
function backupOperator(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

describe('authorization', function (): void {
    it('turns a guest away from every route', function (): void {
        $backup = BackupEloquentModel::factory()->create();

        $this->getJson(route('backups.admin.index'))->assertUnauthorized();
        $this->getJson(route('backups.admin.show', $backup->uuid))->assertUnauthorized();
        $this->postJson(route('backups.admin.store'))->assertUnauthorized();
        $this->getJson(route('backups.admin.download', $backup->uuid))->assertUnauthorized();
        $this->deleteJson(route('backups.admin.destroy', $backup->uuid))->assertUnauthorized();
        $this->postJson(route('backups.admin.bulk-delete'), ['uuids' => [$backup->uuid]])->assertUnauthorized();
    });

    it('refuses a signed-in user who holds no backups permission', function (): void {
        $this->actingAs(backupOperator([]))
            ->getJson(route('backups.admin.index'))
            ->assertForbidden();
    });

    it('refuses a reader on the destructive routes', function (): void {
        $backup = BackupEloquentModel::factory()->create();

        $this->actingAs(backupOperator(['VIEW_ANY_BACKUPS', 'VIEW_BACKUPS']))
            ->deleteJson(route('backups.admin.destroy', $backup->uuid))
            ->assertForbidden();
    });
});

describe('listing', function (): void {
    it('paginates the index flat, not nested under a "meta" key', function (): void {
        BackupEloquentModel::factory()->count(3)->create();

        $this->actingAs(backupOperator(['VIEW_ANY_BACKUPS']))
            ->getJson(route('backups.admin.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'from', 'to', 'total'])
            ->assertJsonMissingPath('meta');
    });

    it('never exposes the auto-increment key', function (): void {
        BackupEloquentModel::factory()->create();

        $response = $this->actingAs(backupOperator(['VIEW_ANY_BACKUPS']))
            ->getJson(route('backups.admin.index'))
            ->assertOk();

        expect($response->json('data.0'))->not->toHaveKey('id');
    });

    it('filters by run status', function (): void {
        BackupEloquentModel::factory()->create();
        BackupEloquentModel::factory()->failed()->create();

        $this->actingAs(backupOperator(['VIEW_ANY_BACKUPS']))
            ->getJson(route('backups.admin.index', ['status' => 'failed']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'failed');
    });

    it('filters by search term against the filename', function (): void {
        BackupEloquentModel::factory()->create(['filename' => 'nightly-2026.zip']);
        BackupEloquentModel::factory()->create(['filename' => 'adhoc-2026.zip']);

        $this->actingAs(backupOperator(['VIEW_ANY_BACKUPS']))
            ->getJson(route('backups.admin.index', ['search' => 'nightly']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filename', 'nightly-2026.zip');
    });

    it('serializes a human-readable size alongside the byte count', function (): void {
        BackupEloquentModel::factory()->create(['size_bytes' => 1_572_864]);

        $this->actingAs(backupOperator(['VIEW_ANY_BACKUPS']))
            ->getJson(route('backups.admin.index'))
            ->assertOk()
            ->assertJsonPath('data.0.size_bytes', 1_572_864)
            ->assertJsonPath('data.0.human_size', '1.5 MB');
    });
});

describe('showing', function (): void {
    it('returns a single backup by uuid', function (): void {
        $backup = BackupEloquentModel::factory()->create();

        $this->actingAs(backupOperator(['VIEW_BACKUPS']))
            ->getJson(route('backups.admin.show', $backup->uuid))
            ->assertOk()
            ->assertJsonPath('uuid', $backup->uuid)
            ->assertJsonPath('filename', $backup->filename);
    });

    it('404s for an unknown uuid', function (): void {
        $this->actingAs(backupOperator(['VIEW_BACKUPS']))
            ->getJson(route('backups.admin.show', '00000000-0000-7000-8000-000000000000'))
            ->assertNotFound();
    });
});

describe('downloading', function (): void {
    it('streams the archive file from its disk', function (): void {
        Storage::fake('r2');
        Storage::disk('r2')->put('argenis-hub/db.zip', 'ARCHIVE-BYTES');

        $backup = BackupEloquentModel::factory()->create([
            'disk' => 'r2',
            'path' => 'argenis-hub/db.zip',
            'filename' => 'db.zip',
        ]);

        $this->actingAs(backupOperator(['DOWNLOAD_BACKUPS']))
            ->get(route('backups.admin.download', $backup->uuid))
            ->assertOk()
            ->assertDownload('db.zip');
    });

    it('404s when the archive file is missing', function (): void {
        Storage::fake('r2');

        $backup = BackupEloquentModel::factory()->create(['disk' => 'r2', 'path' => 'argenis-hub/gone.zip']);

        $this->actingAs(backupOperator(['DOWNLOAD_BACKUPS']))
            ->get(route('backups.admin.download', $backup->uuid))
            ->assertNotFound();
    });
});

describe('deleting', function (): void {
    it('hard-deletes the row and removes the archive file', function (): void {
        Storage::fake('r2');
        Storage::disk('r2')->put('argenis-hub/db.zip', 'ARCHIVE-BYTES');

        $backup = BackupEloquentModel::factory()->create(['disk' => 'r2', 'path' => 'argenis-hub/db.zip']);

        $this->actingAs(backupOperator(['DELETE_BACKUPS']))
            ->deleteJson(route('backups.admin.destroy', $backup->uuid))
            ->assertNoContent();

        expect(BackupEloquentModel::query()->whereKey($backup->getKey())->exists())->toBeFalse();
        Storage::disk('r2')->assertMissing('argenis-hub/db.zip');
    });

    it('records the deletion in the audit trail', function (): void {
        Storage::fake('r2');
        $backup = BackupEloquentModel::factory()->create(['disk' => 'r2', 'path' => null]);

        $this->actingAs(backupOperator(['DELETE_BACKUPS']))
            ->deleteJson(route('backups.admin.destroy', $backup->uuid))
            ->assertNoContent();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'backups.backup',
            'description' => 'backup.deleted',
        ]);
    });
});

describe('bulk operations', function (): void {
    it('bulk hard-deletes the selected backups and their files', function (): void {
        Storage::fake('r2');
        $backups = collect(range(1, 3))->map(function (int $i): BackupEloquentModel {
            Storage::disk('r2')->put("argenis-hub/db-{$i}.zip", 'BYTES');

            return BackupEloquentModel::factory()->create(['disk' => 'r2', 'path' => "argenis-hub/db-{$i}.zip"]);
        });

        $this->actingAs(backupOperator(['BULK_DELETE_BACKUPS']))
            ->postJson(route('backups.admin.bulk-delete'), ['uuids' => $backups->pluck('uuid')->all()])
            ->assertOk()
            ->assertJsonPath('deleted', 3);

        expect(BackupEloquentModel::query()->count())->toBe(0);
        Storage::disk('r2')->assertMissing('argenis-hub/db-1.zip');
    });

    it('refuses bulk delete without the bulk permission', function (): void {
        $backup = BackupEloquentModel::factory()->create();

        $this->actingAs(backupOperator(['DELETE_BACKUPS']))
            ->postJson(route('backups.admin.bulk-delete'), ['uuids' => [$backup->uuid]])
            ->assertForbidden();
    });

    it('rejects an empty uuid list', function (): void {
        $this->actingAs(backupOperator(['BULK_DELETE_BACKUPS']))
            ->postJson(route('backups.admin.bulk-delete'), ['uuids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('uuids');
    });

    it('rejects a malformed uuid in the list', function (): void {
        $backup = BackupEloquentModel::factory()->create();

        $this->actingAs(backupOperator(['BULK_DELETE_BACKUPS']))
            ->postJson(route('backups.admin.bulk-delete'), ['uuids' => [$backup->uuid, 'not-a-uuid']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('uuids.1');
    });
});
