<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @internal Application code goes through the module's repository port, not this model.
 *
 * A normalized interval actually removed from the result, carrying the union of
 * the reasons and origins of the decisions merged into it (FR-5). Append-only.
 *
 * @property int $id
 * @property int $video_edit_id
 * @property int $sequence
 * @property int $start_ms
 * @property int $end_ms
 * @property list<string> $reasons
 * @property list<string> $origins
 * @property Carbon|null $created_at
 * @property-read VideoEditEloquentModel $videoEdit
 *
 * @mixin \Eloquent
 */
#[Table('video_edit_applied_cuts')]
#[Fillable([
    'video_edit_id',
    'sequence',
    'start_ms',
    'end_ms',
    'reasons',
    'origins',
])]
final class VideoEditAppliedCutEloquentModel extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'video_edit_id'];

    /**
     * @return BelongsTo<VideoEditEloquentModel, $this>
     */
    public function videoEdit(): BelongsTo
    {
        return $this->belongsTo(VideoEditEloquentModel::class, 'video_edit_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'video_edit_id' => 'integer',
            'sequence' => 'integer',
            'start_ms' => 'integer',
            'end_ms' => 'integer',
            'reasons' => 'array',
            'origins' => 'array',
        ];
    }
}
