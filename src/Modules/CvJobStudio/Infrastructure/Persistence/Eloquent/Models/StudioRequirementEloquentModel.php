<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
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
 * @mixin \Eloquent
 *
 * @internal
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
    public function scopeOwnedBy($query, int $userId)
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
