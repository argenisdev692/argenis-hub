<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('refuses to back up a non-pgsql connection', function (): void {
    config()->set('database.default', 'sqlite');

    $this->artisan('lead-scout:backup', ['--dir' => sys_get_temp_dir()])->assertFailed();
});

it('dumps scout_* tables and keeps the 4 newest copies', function (): void {
    config()->set('database.connections.pgsql_backup', [
        'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => '5432',
        'database' => 'backup_test', 'username' => 'postgres', 'password' => 'secret',
    ]);

    Process::fake(['*' => Process::result(output: 'dumped')]);

    $dir = sys_get_temp_dir().'/lead-scout-backup-test-'.uniqid();
    mkdir($dir, 0755, true);

    for ($i = 1; $i <= 5; $i++) {
        touch($dir.'/lead-scout-2026010'.$i.'-050000.dump');
    }

    $this->artisan('lead-scout:backup', ['--dir' => $dir, '--connection' => 'pgsql_backup'])->assertSuccessful();

    Process::assertRan(function ($process): bool {
        $command = implode(' ', (array) $process->command);

        return str_contains($command, 'pg_dump') && str_contains($command, 'scout_');
    });

    $remaining = glob($dir.'/*.dump') ?: [];
    $names = array_map(basename(...), $remaining);

    expect($remaining)->toHaveCount(4)
        ->and($names)->not->toContain('lead-scout-20260101-050000.dump');

    foreach ($remaining as $file) {
        unlink($file);
    }
    rmdir($dir);
});

it('refuses destinations inside the git-tracked tree', function (): void {
    config()->set('database.connections.pgsql_backup', [
        'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => '5432',
        'database' => 'backup_test', 'username' => 'postgres', 'password' => 'secret',
    ]);

    Process::fake(['*' => Process::result(output: 'dumped')]);

    $this->artisan('lead-scout:backup', ['--dir' => base_path('docs'), '--connection' => 'pgsql_backup'])->assertFailed();
});
