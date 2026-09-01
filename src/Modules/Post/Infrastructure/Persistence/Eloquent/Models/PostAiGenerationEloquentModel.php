<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Database\Factories\PostAiGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Post\Domain\Enums\PostAiGenerationStatus;
use Modules\Post\Domain\Enums\PostImageMode;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property string $topic
 * @property string|null $angle
 * @property string|null $key_trend
 * @property string $provider
 * @property PostImageMode $image_mode
 * @property PostAiGenerationStatus $status
 * @property string|null $stage_message
 * @property int $progress
 * @property int $iteration
 * @property array<string, mixed>|null $result
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $causer
 *
 * @mixin \Eloquent
 */
#[Table('post_ai_generations')]
#[Fillable([
    'uuid', 'topic', 'angle', 'key_trend', 'provider', 'image_mode', 'status',
    'stage_message', 'progress', 'iteration', 'result', 'error_message',
    'started_at', 'finished_at', 'created_by',
])]
final class PostAiGenerationEloquentModel extends Model
{
    /** @use HasFactory<PostAiGenerationFactory> */
    use HasFactory, LogsActivity;

    /**
     * `result` is a whole generated draft — hundreds of lines of HTML. It is
     * fetched deliberately by the status endpoint, never leaked into a list.
     *
     * @var list<string>
     */
    protected $hidden = ['id', 'created_by'];

    protected static function booted(): void
    {
        self::creating(function (PostAiGenerationEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostAiGenerationStatus::class,
            'image_mode' => PostImageMode::class,
            'result' => 'array',
            'progress' => 'integer',
            'iteration' => 'integer',
            'created_by' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Only the lifecycle is audited. `result` is deliberately excluded — it is
     * a large blob that would be duplicated into `activity_log` on every phase
     * transition, and the draft itself is already reachable on this row.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'iteration', 'error_message'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('post');
    }

    protected static function newFactory(): PostAiGenerationFactory
    {
        return PostAiGenerationFactory::new();
    }
}
