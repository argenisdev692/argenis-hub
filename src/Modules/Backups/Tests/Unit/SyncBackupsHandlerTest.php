<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Backups\Application\Commands\SyncBackupsHandler;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;

/**
 * `SyncBackupsHandler` reconciles the `backups` index with the archives on the
 * backup disk: every `.zip` becomes a `Completed` row, orphaned `Completed` rows
 * are pruned, and `Failed` rows are left alone.
 */
beforeEach(function (): void {
    Storage::fake('r2');
    $this->prefix = (string) config('backup.backup.name');
});

function sync(): int
{
    return app(SyncBackupsHandler::class)->handle();
}

it('creates a completed row for every archive on disk', function (): void {
    Storage::disk('r2')->put("{$this->prefix}/a.zip", str_repeat('x', 2048));
    Storage::disk('r2')->put("{$this->prefix}/b.zip", str_repeat('x', 4096));
    Storage::disk('r2')->put("{$this->prefix}/notes.txt", 'ignore me');

    expect(sync())->toBe(2);

    expect(BackupEloquentModel::query()->where('status', 'completed')->count())->toBe(2);

    $a = BackupEloquentModel::query()->where('filename', 'a.zip')->firstOrFail();
    expect($a->disk)->toBe('r2')
        ->and($a->path)->toBe("{$this->prefix}/a.zip")
        ->and($a->size_bytes)->toBe(2048);
});

it('is idempotent — a second run does not duplicate rows', function (): void {
    Storage::disk('r2')->put("{$this->prefix}/a.zip", 'data');

    sync();
    sync();

    expect(BackupEloquentModel::query()->count())->toBe(1);
});

it('prunes a completed row whose archive has been removed', function (): void {
    Storage::disk('r2')->put("{$this->prefix}/a.zip", 'data');
    Storage::disk('r2')->put("{$this->prefix}/b.zip", 'data');
    sync();

    Storage::disk('r2')->delete("{$this->prefix}/a.zip");

    expect(sync())->toBe(1);
    expect(BackupEloquentModel::query()->pluck('filename')->all())->toBe(['b.zip']);
});

it('prunes every completed row when the disk is empty', function (): void {
    Storage::disk('r2')->put("{$this->prefix}/a.zip", 'data');
    sync();

    Storage::disk('r2')->delete("{$this->prefix}/a.zip");

    expect(sync())->toBe(0);
    expect(BackupEloquentModel::query()->where('status', 'completed')->count())->toBe(0);
});

it('leaves failed rows untouched', function (): void {
    BackupEloquentModel::factory()->failed()->create(['filename' => 'failed-run']);

    sync();

    expect(BackupEloquentModel::query()->where('status', 'failed')->count())->toBe(1);
});
