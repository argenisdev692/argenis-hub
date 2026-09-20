<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Per-call provider audit: cost and status, never payloads (OWASP §9).
 *
 * Pruned after 180 days; the monthly budget aggregates remain.
 *
 * @property int $id
 * @property string $uuid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @internal
 *
 * @property int $user_id
 * @property int|null $run_id
 * @property string $category
 * @property string $provider
 * @property string|null $operation
 * @property string|null $purpose
 * @property int $attempt_no
 * @property string|null $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_input_tokens
 * @property int $cache_creation_input_tokens
 * @property int $cost_micros
 * @property bool $succeeded
 * @property int|null $http_status
 * @property CarbonImmutable|null $called_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read User|null $user
 *
 * @method static Builder<static>|StudioProviderCallEloquentModel newModelQuery()
 * @method static Builder<static>|StudioProviderCallEloquentModel newQuery()
 * @method static Builder<static>|StudioProviderCallEloquentModel onlyTrashed()
 * @method static Builder<static>|StudioProviderCallEloquentModel query()
 * @method static Builder<static>|StudioProviderCallEloquentModel whereAttemptNo($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereCacheCreationInputTokens($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereCacheReadInputTokens($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereCalledAt($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereCategory($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereCostMicros($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereCreatedAt($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereDeletedAt($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereHttpStatus($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereId($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereInputTokens($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereModel($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereOperation($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereOutputTokens($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereProvider($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel wherePurpose($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereRunId($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereSucceeded($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereUpdatedAt($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereUserId($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel whereUuid($value)
 * @method static Builder<static>|StudioProviderCallEloquentModel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudioProviderCallEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_provider_calls')]
#[Fillable([
    'uuid', 'user_id', 'run_id', 'category', 'provider', 'operation', 'purpose', 'attempt_no',
    'model', 'input_tokens', 'output_tokens', 'cache_read_input_tokens',
    'cache_creation_input_tokens', 'cost_micros', 'succeeded', 'http_status', 'called_at',
])]
final class StudioProviderCallEloquentModel extends Model
{
    use MassPrunable, SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioProviderCallEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Builder<StudioProviderCallEloquentModel> */
    public function prunable(): Builder
    {
        return self::where('called_at', '<=', now()->subDays(180));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'run_id' => 'integer',
            'attempt_no' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_read_input_tokens' => 'integer',
            'cache_creation_input_tokens' => 'integer',
            'cost_micros' => 'integer',
            'succeeded' => 'boolean',
            'http_status' => 'integer',
            'called_at' => 'datetime',
        ];
    }
}
