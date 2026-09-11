<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\VideoEditSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @internal Application code goes through the module's repository port, not this model.
 *
 * One uploaded clip of an edit. The file itself is transient (deleted on
 * success, or 24 h after a failure); the metadata — including the content
 * fingerprint used by V2 transcript reuse (EX-6) — stays with the edit.
 *
 * @property int $id
 * @property string $uuid
 * @property int $video_edit_id
 * @property int $position
 * @property string $original_name
 * @property string $extension
 * @property string $declared_mime
 * @property int $declared_size_bytes
 * @property string|null $storage_path
 * @property int|null $size_bytes
 * @property string|null $sha256
 * @property int|null $duration_ms
 * @property int|null $width
 * @property int|null $height
 * @property string|null $frame_rate
 * @property bool|null $has_audio
 * @property string|null $container
 * @property string|null $video_codec
 * @property string|null $audio_codec
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VideoEditEloquentModel $videoEdit
 *
 * @mixin \Eloquent
 */
#[Table('video_edit_sources')]
#[Fillable([
    'uuid',
    'video_edit_id',
    'position',
    'original_name',
    'extension',
    'declared_mime',
    'declared_size_bytes',
    'storage_path',
    'size_bytes',
    'sha256',
    'duration_ms',
    'width',
    'height',
    'frame_rate',
    'has_audio',
    'container',
    'video_codec',
    'audio_codec',
])]
final class VideoEditSourceEloquentModel extends Model
{
    /** @use HasFactory<VideoEditSourceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'video_edit_id', 'storage_path'];

    protected static function booted(): void
    {
        self::creating(function (VideoEditSourceEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

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
            'position' => 'integer',
            'declared_size_bytes' => 'integer',
            'size_bytes' => 'integer',
            'duration_ms' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'frame_rate' => 'decimal:3',
            'has_audio' => 'boolean',
        ];
    }

    protected static function newFactory(): VideoEditSourceFactory
    {
        return VideoEditSourceFactory::new();
    }
}
