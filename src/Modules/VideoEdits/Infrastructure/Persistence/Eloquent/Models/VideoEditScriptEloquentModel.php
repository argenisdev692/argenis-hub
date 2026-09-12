<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @internal Application code goes through the module's ports, not this model.
 *
 * Deleted with its edit through the FK cascade (Q5): a script is the user's own
 * course material and must not outlive the edit it was attached to.
 *
 * @property int $id
 * @property int $video_edit_id
 * @property string $uuid
 * @property string $original_name
 * @property string $extension
 * @property string $declared_mime
 * @property int $declared_size_bytes
 * @property string $storage_path
 * @property string|null $extracted_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VideoEditEloquentModel $videoEdit
 *
 * @mixin \Eloquent
 */
#[Table('video_edit_scripts')]
#[Fillable([
    'video_edit_id',
    'uuid',
    'original_name',
    'extension',
    'declared_mime',
    'declared_size_bytes',
    'storage_path',
    'extracted_text',
])]
final class VideoEditScriptEloquentModel extends Model
{
    /**
     * The object path never leaves the backend (OWASP §12).
     *
     * @var list<string>
     */
    protected $hidden = ['id', 'video_edit_id', 'storage_path'];

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
            'declared_size_bytes' => 'integer',
        ];
    }
}
