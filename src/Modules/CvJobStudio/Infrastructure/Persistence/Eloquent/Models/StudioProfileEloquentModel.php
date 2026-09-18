<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Database\Factories\StudioProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string $flow
 * @property bool $is_active
 * @property array<array-key, mixed>|null $rules
 * @property int $rules_version
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, StudioPostingEloquentModel> $postings
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('studio_profiles')]
#[Fillable([
    'uuid',
    'user_id',
    'name',
    'slug',
    'flow',
    'is_active',
    'base_city',
    'base_country',
    'accepted_remote_scopes',
    'stack_must',
    'stack_reject',
    'never_seed',
    'search_languages',
    'geography_prefer',
    'geography_deny',
    'years_baseline',
    'education_level',
    'language_levels',
    'protected_block',
    'seniority_band',
    'tone',
    'rules',
    'rules_version',
])]
final class StudioProfileEloquentModel extends Model
{
    /** @use HasFactory<StudioProfileFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioProfileEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StudioPostingEloquentModel, $this> */
    public function postings(): HasMany
    {
        return $this->hasMany(StudioPostingEloquentModel::class, 'profile_id');
    }

    /**
     * @param  Builder<StudioProfileEloquentModel>  $query
     * @return Builder<StudioProfileEloquentModel>
     */
    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'is_active' => 'boolean',
            'accepted_remote_scopes' => 'array',
            'stack_must' => 'array',
            'stack_reject' => 'array',
            'never_seed' => 'array',
            'search_languages' => 'array',
            'geography_prefer' => 'array',
            'geography_deny' => 'array',
            'years_baseline' => 'integer',
            'language_levels' => 'array',
            'protected_block' => 'array',
            'seniority_band' => 'array',
            'rules' => 'array',
            'rules_version' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'flow', 'is_active', 'rules_version'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('studio.profiles');
    }

    protected static function newFactory(): StudioProfileFactory
    {
        return StudioProfileFactory::new();
    }
}
