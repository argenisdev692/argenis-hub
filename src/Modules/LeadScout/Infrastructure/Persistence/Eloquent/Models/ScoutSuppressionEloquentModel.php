<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ScoutSuppressionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\LeadScout\Domain\Enums\SuppressionSource;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Do-not-contact registry with absolute precedence (spec FR-17, FR-43).
 * Never lifted from the web — only via `lead-scout:privacy lift-suppression`.
 *
 * @property int $id
 * @property string $uuid
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_suppressions')]
#[Fillable([
    'uuid',
    'canonical_domain',
    'tax_id',
    'name',
    'source',
    'reason',
    'list_period',
])]
final class ScoutSuppressionEloquentModel extends Model
{
    /** @use HasFactory<ScoutSuppressionFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $hidden = ['id'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['source' => SuppressionSource::class];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['canonical_domain', 'tax_id', 'source', 'reason', 'list_period'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('lead-scout.suppression');
    }

    protected static function newFactory(): ScoutSuppressionFactory
    {
        return ScoutSuppressionFactory::new();
    }
}
