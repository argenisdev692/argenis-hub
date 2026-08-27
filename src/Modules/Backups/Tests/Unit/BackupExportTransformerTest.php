<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Modules\Backups\Domain\Enums\BackupStatus;
use Modules\Backups\Infrastructure\Http\Export\BackupExportTransformer;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;

it('exposes a stable header set', function (): void {
    expect(BackupExportTransformer::headers())
        ->toBe(['Filename', 'Disk', 'Size', 'Status', 'Connection', 'Started', 'Finished', 'Created']);
});

it('maps a completed backup row to human-readable columns', function (): void {
    $backup = BackupEloquentModel::factory()->make([
        'filename' => 'nightly-db.zip',
        'disk' => 'r2',
        'size_bytes' => 1_572_864,
        'status' => BackupStatus::Completed,
        'connection' => 'mysql',
        'started_at' => Carbon::parse('2026-08-27 02:00:00'),
        'finished_at' => Carbon::parse('2026-08-27 02:01:30'),
        'created_at' => Carbon::parse('2026-08-27 02:00:00'),
    ]);

    expect(BackupExportTransformer::toRow($backup))->toBe([
        'Filename' => 'nightly-db.zip',
        'Disk' => 'r2',
        'Size' => '1.5 MB',
        'Status' => 'Completed',
        'Connection' => 'mysql',
        'Started' => 'August 27, 2026 02:00',
        'Finished' => 'August 27, 2026 02:01',
        'Created' => 'August 27, 2026 02:00',
    ]);
});

it('renders dashes for the missing values on a failed attempt', function (): void {
    $backup = BackupEloquentModel::factory()->make([
        'filename' => 'failed-2026-08-27-02-00-00',
        'size_bytes' => null,
        'status' => BackupStatus::Failed,
        'connection' => null,
        'started_at' => Carbon::parse('2026-08-27 02:00:00'),
        'finished_at' => null,
    ]);

    $row = BackupExportTransformer::toRow($backup);

    expect($row['Size'])->toBe('—')
        ->and($row['Status'])->toBe('Failed')
        ->and($row['Connection'])->toBe('—')
        ->and($row['Finished'])->toBe('—');
});
