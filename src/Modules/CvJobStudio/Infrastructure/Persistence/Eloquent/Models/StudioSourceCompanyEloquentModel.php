<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
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
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('studio_source_companies')]
#[Fillable(['uuid', 'user_id', 'source_id', 'slug', 'company_name', 'status', 'ats_kind', 'discovered_via', 'verified_at', 'job_count_last_seen', 'last_seen_at'])]
final class StudioSourceCompanyEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioSourceCompanyEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioSourceEloquentModel, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(StudioSourceEloquentModel::class, 'source_id');
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
            'source_id' => 'integer',
            'verified_at' => 'datetime',
            'job_count_last_seen' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }
}
