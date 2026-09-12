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
 * Deleted with its edit through the FK cascade (Q5) — a transcript is the
 * user's speech verbatim and must not outlive the edit it belongs to.
 *
 * @property int $id
 * @property int $video_edit_id
 * @property string $source_fingerprint
 * @property string $provider
 * @property string $model
 * @property string|null $language
 * @property int $word_count
 * @property array<string, mixed> $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VideoEditEloquentModel $videoEdit
 *
 * @mixin \Eloquent
 */
#[Table('video_edit_transcripts')]
#[Fillable([
    'video_edit_id',
    'source_fingerprint',
    'provider',
    'model',
    'language',
    'word_count',
    'payload',
])]
final class VideoEditTranscriptEloquentModel extends Model
{
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
            'word_count' => 'integer',
            'payload' => 'array',
        ];
    }
}
