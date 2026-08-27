<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Persistence\Eloquent\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BackupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Backups\Application\DTOs\BackupFilterData;
use Modules\Backups\Domain\Enums\BackupStatus;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property string $disk
 * @property string|null $path
 * @property string $filename
 * @property int|null $size_bytes
 * @property BackupStatus $status
 * @property string|null $connection
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> applyFilters(BackupFilterData $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BackupEloquentModel query()
 * @method static BackupFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Fillable(['uuid', 'disk', 'path', 'filename', 'size_bytes', 'status', 'connection', 'error', 'started_at', 'finished_at'])]
#[Hidden(['id'])]
final class BackupEloquentModel extends Model
{
    /** @use HasFactory<BackupFactory> */
    use HasFactory;

    use LogsActivity;

    protected $table = 'backups';

    protected static function newFactory(): BackupFactory
    {
        return BackupFactory::new();
    }

    protected static function booted(): void
    {
        self::creating(function (self $backup): void {
            if (empty($backup->uuid)) {
                $backup->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'status' => BackupStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['disk', 'path', 'filename', 'size_bytes', 'status', 'connection', 'error', 'started_at', 'finished_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('backups.backup');
    }

    /**
     * Canonical search + status + date-range + sort contract
     * (`BACKEND-PHP/SKILL.md` §5.2). There is no `deleted_at` branch — the
     * table is hard-deleted — so `status` filters the run outcome directly.
     */
    public function scopeApplyFilters(Builder $query, BackupFilterData $f): Builder
    {
        return $query
            ->when($f->search, fn (Builder $q, string $s) => $q->where(function (Builder $q) use ($s): void {
                $q->where('filename', 'like', "%{$s}%")
                    ->orWhere('connection', 'like', "%{$s}%")
                    ->orWhere('disk', 'like', "%{$s}%");
            }))
            ->when(
                in_array($f->status, ['running', 'completed', 'failed'], true),
                fn (Builder $q) => $q->where('status', $f->status),
            )
            ->when($f->dateFrom && $f->dateTo, fn (Builder $q) => $q->whereBetween('created_at', [
                CarbonImmutable::parse($f->dateFrom)->startOfDay(),
                CarbonImmutable::parse($f->dateTo)->endOfDay(),
            ]))
            ->when($f->dateFrom && ! $f->dateTo, fn (Builder $q) => $q->where(
                'created_at', '>=', CarbonImmutable::parse($f->dateFrom)->startOfDay(),
            ))
            ->when(! $f->dateFrom && $f->dateTo, fn (Builder $q) => $q->where(
                'created_at', '<=', CarbonImmutable::parse($f->dateTo)->endOfDay(),
            ))
            ->orderBy($f->sortField, $f->sortOrder === 1 ? 'asc' : 'desc');
    }
}
