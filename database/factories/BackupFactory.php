<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Backups\Domain\Enums\BackupStatus;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;

/**
 * @extends Factory<BackupEloquentModel>
 */
final class BackupFactory extends Factory
{
    protected $model = BackupEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', 'now'));
        $prefix = (string) config('backup.backup.name', 'laravel-backup');
        $filename = $startedAt->format('Y-m-d-H-i-s').'.zip';

        return [
            'uuid' => (string) Str::uuid7(),
            'disk' => (string) (config('backup.backup.destination.disks.0') ?? 'local'),
            'path' => $prefix.'/'.$filename,
            'filename' => $filename,
            'size_bytes' => fake()->numberBetween(50_000, 80_000_000),
            'status' => BackupStatus::Completed,
            'connection' => 'sqlite',
            'error' => null,
            'started_at' => $startedAt,
            'finished_at' => $startedAt->addMinutes(2),
        ];
    }

    public function failed(): self
    {
        return $this->state(fn (): array => [
            'path' => null,
            'filename' => 'failed-'.now()->format('Y-m-d-H-i-s'),
            'size_bytes' => null,
            'status' => BackupStatus::Failed,
            'error' => 'mysqldump: command not found',
            'finished_at' => now(),
        ]);
    }

    public function running(): self
    {
        return $this->state(fn (): array => [
            'path' => null,
            'size_bytes' => null,
            'status' => BackupStatus::Running,
            'finished_at' => null,
        ]);
    }
}
