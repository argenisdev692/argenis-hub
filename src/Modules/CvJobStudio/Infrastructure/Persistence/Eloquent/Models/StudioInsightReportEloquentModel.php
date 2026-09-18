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
#[Table('studio_insight_reports')]
#[Fillable(['uuid', 'user_id', 'run_id', 'jds_analyzed', 'sample_note', 'requirements', 'reach_table', 'recommendations', 'outcome_correlation', 'rules_version'])]
final class StudioInsightReportEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioInsightReportEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioRunEloquentModel, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(StudioRunEloquentModel::class, 'run_id');
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
            'run_id' => 'integer',
            'jds_analyzed' => 'integer',
            'requirements' => 'array',
            'reach_table' => 'array',
            'recommendations' => 'array',
            'outcome_correlation' => 'array',
            'rules_version' => 'integer',
        ];
    }
}
