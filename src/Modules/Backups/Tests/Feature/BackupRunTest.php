<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Backups\Application\Contracts\BackupRunOutcome;
use Modules\Backups\Application\Contracts\DatabaseBackupRunner;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;
use Modules\Backups\Infrastructure\Queue\RunDatabaseBackupJob;

/**
 * `POST /data/admin/backups` — triggers an on-demand database backup. The slow
 * work is a queued job; the queue runs synchronously under the test config, so
 * binding a fake {@see DatabaseBackupRunner} exercises the whole job end to end
 * without a real database dumper.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  list<string>  $permissions
 */
function backupRunner(array $permissions = ['CREATE_BACKUPS']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function fakeRunner(int $exitCode, string $output): void
{
    app()->bind(DatabaseBackupRunner::class, fn (): DatabaseBackupRunner => new class($exitCode, $output) implements DatabaseBackupRunner
    {
        public function __construct(private int $exitCode, private string $output) {}

        public function run(): BackupRunOutcome
        {
            return new BackupRunOutcome($this->exitCode, $this->output);
        }
    });
}

it('turns a guest away', function (): void {
    $this->postJson(route('backups.admin.store'))->assertUnauthorized();
});

it('refuses a signed-in user without CREATE_BACKUPS', function (): void {
    $this->actingAs(backupRunner(['VIEW_ANY_BACKUPS']))
        ->postJson(route('backups.admin.store'))
        ->assertForbidden();
});

it('queues the backup job and returns 202', function (): void {
    Queue::fake();

    $this->actingAs(backupRunner())
        ->postJson(route('backups.admin.store'))
        ->assertStatus(202)
        ->assertJsonPath('status', 'queued');

    Queue::assertPushed(RunDatabaseBackupJob::class);
});

it('records the request in the audit trail', function (): void {
    Queue::fake();

    $this->actingAs(backupRunner())->postJson(route('backups.admin.store'))->assertStatus(202);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'backups.backup',
        'description' => 'backup.run_requested',
    ]);
});

it('materialises the new archive as a completed row on a successful run', function (): void {
    Storage::fake('r2');
    $prefix = (string) config('backup.backup.name');
    Storage::disk('r2')->put("{$prefix}/2026-08-27-02-00-00.zip", 'ARCHIVE-BYTES');
    fakeRunner(0, 'Backup completed!');

    $this->actingAs(backupRunner())->postJson(route('backups.admin.store'))->assertStatus(202);

    $row = BackupEloquentModel::query()->where('status', 'completed')->first();
    expect($row)->not->toBeNull()
        ->and($row->filename)->toBe('2026-08-27-02-00-00.zip')
        ->and($row->disk)->toBe('r2');

    $this->assertDatabaseHas('activity_log', ['description' => 'backup.run_succeeded']);
});

it('writes a failed row carrying the runner output when the run exits non-zero', function (): void {
    Storage::fake('r2');
    fakeRunner(1, 'mysqldump: command not found');

    $this->actingAs(backupRunner())->postJson(route('backups.admin.store'))->assertStatus(202);

    $row = BackupEloquentModel::query()->where('status', 'failed')->first();
    expect($row)->not->toBeNull()
        ->and($row->path)->toBeNull()
        ->and($row->error)->toContain('mysqldump: command not found');

    $this->assertDatabaseHas('activity_log', ['description' => 'backup.run_failed']);
});
