<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @internal
 *
 * @property int $user_id
 * @property int $profile_id
 * @property string|null $country
 * @property string|null $language
 * @property string|null $title_variant
 * @property string|null $stack_synonym
 * @property string|null $seniority
 * @property string|null $modality
 * @property string|null $regional_term
 * @property string $template
 * @property CarbonImmutable|null $deleted_at
 * @property-read StudioProfileEloquentModel|null $profile
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereModality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereRegionalTerm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereSeniority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereStackSynonym($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereTemplate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereTitleVariant($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryTemplateEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_query_templates')]
#[Fillable(['uuid', 'user_id', 'profile_id', 'country', 'language', 'title_variant', 'stack_synonym', 'seniority', 'modality', 'regional_term', 'template'])]
final class StudioQueryTemplateEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioQueryTemplateEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioProfileEloquentModel, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(StudioProfileEloquentModel::class, 'profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'profile_id' => 'integer'];
    }
}
