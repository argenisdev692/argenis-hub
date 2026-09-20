<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Domain\Enums\RequirementNature;
use Modules\CvJobStudio\Domain\Enums\RequirementTag;

/**
 * @property int $id
 * @property string $uuid
 * @property int $posting_id
 * @property string $canonical_name
 * @property RequirementTag $tag
 * @property RequirementNature $nature
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StudioPostingEloquentModel $posting
 *
 * @internal
 *
 * @property int $user_id
 * @property string|null $raw_text
 * @property numeric $weight
 * @property numeric $normalized_weight
 * @property string|null $source_text_hash
 * @property string|null $extracted_by_provider
 * @property string|null $extracted_by_model
 * @property string|null $prompt_version
 * @property CarbonImmutable|null $deleted_at
 * @property-read User|null $user
 *
 * @method static Builder<static>|StudioRequirementEloquentModel newModelQuery()
 * @method static Builder<static>|StudioRequirementEloquentModel newQuery()
 * @method static Builder<static>|StudioRequirementEloquentModel onlyTrashed()
 * @method static Builder<static>|StudioRequirementEloquentModel ownedBy(int $userId)
 * @method static Builder<static>|StudioRequirementEloquentModel query()
 * @method static Builder<static>|StudioRequirementEloquentModel whereCanonicalName($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereCreatedAt($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereDeletedAt($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereExtractedByModel($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereExtractedByProvider($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereId($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereNature($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereNormalizedWeight($value)
 * @method static Builder<static>|StudioRequirementEloquentModel wherePostingId($value)
 * @method static Builder<static>|StudioRequirementEloquentModel wherePromptVersion($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereRawText($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereSourceTextHash($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereTag($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereUpdatedAt($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereUserId($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereUuid($value)
 * @method static Builder<static>|StudioRequirementEloquentModel whereWeight($value)
 * @method static Builder<static>|StudioRequirementEloquentModel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudioRequirementEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_requirements')]
#[Fillable([
    'uuid',
    'user_id',
    'posting_id',
    'canonical_name',
    'raw_text',
    'tag',
    'nature',
    'weight',
    'normalized_weight',
    'source_text_hash',
    'extracted_by_provider',
    'extracted_by_model',
    'prompt_version',
])]
final class StudioRequirementEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioRequirementEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioPostingEloquentModel, $this> */
    public function posting(): BelongsTo
    {
        return $this->belongsTo(StudioPostingEloquentModel::class, 'posting_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<StudioRequirementEloquentModel>  $query
     * @return Builder<StudioRequirementEloquentModel>
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
            'posting_id' => 'integer',
            'tag' => RequirementTag::class,
            'nature' => RequirementNature::class,
            'weight' => 'decimal:4',
            'normalized_weight' => 'decimal:6',
        ];
    }
}
