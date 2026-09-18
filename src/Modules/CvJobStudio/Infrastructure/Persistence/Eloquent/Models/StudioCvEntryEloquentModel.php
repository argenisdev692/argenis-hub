<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('studio_cv_entries')]
#[Fillable(['uuid', 'user_id', 'structure_id', 'kind', 'ordinal', 'organization', 'role_title', 'location', 'started_on', 'ended_on', 'is_current', 'is_protected'])]
final class StudioCvEntryEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioCvEntryEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioCvStructureEloquentModel, $this> */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(StudioCvStructureEloquentModel::class, 'structure_id');
    }

    /** @return HasMany<StudioCvBulletEloquentModel, $this> */
    public function bullets(): HasMany
    {
        return $this->hasMany(StudioCvBulletEloquentModel::class, 'entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'structure_id' => 'integer',
            'ordinal' => 'integer',
            'started_on' => 'date',
            'ended_on' => 'date',
            'is_current' => 'boolean',
            'is_protected' => 'boolean',
        ];
    }
}
