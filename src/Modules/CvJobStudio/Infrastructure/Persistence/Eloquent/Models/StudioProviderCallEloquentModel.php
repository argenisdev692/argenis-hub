<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
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
 * Pruned after 180 days; the monthly budget aggregates remain.
 *
 * @property int $id
 * @property string $uuid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 *
 * @internal
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
