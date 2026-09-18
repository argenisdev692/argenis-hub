<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
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
 * @mixin \Eloquent
 *
 * @internal
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
    public function scopeOwnedBy($query, int $userId)
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
