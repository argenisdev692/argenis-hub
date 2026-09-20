<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A generated derivative of a CV — rewrite or tailored variant. The source CV
 * is never overwritten; the version chain (`parent_version_id`) plus the
 * content snapshot make every document reproducible from stored data alone
 * (NFR-11). `bullet_provenance` records which source bullet each output
 * bullet derives from (FR-8).
 *
 * @property int $id
 * @property string $uuid
 * @property string $purpose
 * @property string $language
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @internal
 *
 * @property int $user_id
 * @property int $cv_id
 * @property int|null $structure_id
 * @property int|null $profile_id
 * @property int|null $posting_id
 * @property array<array-key, mixed>|null $content
 * @property int|null $page_count_estimate
 * @property int|null $parent_version_id
 * @property array<array-key, mixed>|null $bullet_provenance
 * @property int $rules_version
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, StudioExportEloquentModel> $exports
 * @property-read int|null $exports_count
 * @property-read User|null $user
 *
 * @method static Builder<static>|StudioCvVersionEloquentModel newModelQuery()
 * @method static Builder<static>|StudioCvVersionEloquentModel newQuery()
 * @method static Builder<static>|StudioCvVersionEloquentModel onlyTrashed()
 * @method static Builder<static>|StudioCvVersionEloquentModel ownedBy(int $userId)
 * @method static Builder<static>|StudioCvVersionEloquentModel query()
 * @method static Builder<static>|StudioCvVersionEloquentModel whereBulletProvenance($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereContent($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereCreatedAt($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereCvId($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereDeletedAt($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereId($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereLanguage($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel wherePageCountEstimate($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereParentVersionId($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel wherePostingId($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereProfileId($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel wherePurpose($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereRulesVersion($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereStructureId($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereUpdatedAt($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereUserId($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel whereUuid($value)
 * @method static Builder<static>|StudioCvVersionEloquentModel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudioCvVersionEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_cv_versions')]
#[Fillable([
    'uuid', 'user_id', 'cv_id', 'structure_id', 'profile_id', 'posting_id', 'purpose',
    'language', 'content', 'page_count_estimate', 'parent_version_id', 'bullet_provenance', 'rules_version',
])]
final class StudioCvVersionEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioCvVersionEloquentModel $model): void {
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

    /** @return HasMany<StudioExportEloquentModel, $this> */
    public function exports(): HasMany
    {
        return $this->hasMany(StudioExportEloquentModel::class, 'cv_version_id');
    }

    /**
     * @param  Builder<StudioCvVersionEloquentModel>  $query
     * @return Builder<StudioCvVersionEloquentModel>
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'cv_id' => 'integer',
            'structure_id' => 'integer',
            'profile_id' => 'integer',
            'posting_id' => 'integer',
            'content' => 'array',
            'page_count_estimate' => 'integer',
            'parent_version_id' => 'integer',
            'bullet_provenance' => 'array',
            'rules_version' => 'integer',
        ];
    }
}
