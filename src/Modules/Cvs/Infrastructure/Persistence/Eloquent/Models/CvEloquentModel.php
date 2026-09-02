<?php

declare(strict_types=1);

namespace Modules\Cvs\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CvFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Cvs\Application\DTOs\CvFilterData;
use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Domain\Enums\CvNiche;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $title
 * @property CvNiche $niche
 * @property bool $is_primary
 * @property string $file_path
 * @property CvFileType $file_type
 * @property string $original_filename
 * @property string|null $raw_text
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('cvs')]
#[Fillable([
    'uuid',
    'user_id',
    'title',
    'niche',
    'is_primary',
    'file_path',
    'file_type',
    'original_filename',
    'raw_text',
])]
final class CvEloquentModel extends Model
{
    /** @use HasFactory<CvFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * `id` is internal (uuid is the public identifier); `file_path` is the raw
     * R2 object key, reachable only through a signed URL; `raw_text` is the full
     * extracted résumé — names, addresses, phone numbers, employment history.
     * None of the three may ever reach a response body (OWASP §12).
     *
     * @var list<string>
     */
    protected $hidden = ['id', 'file_path', 'raw_text'];

    protected static function booted(): void
    {
        self::creating(function (CvEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Shared list/export filter (BACKEND-PHP §5.2). Soft-delete `suspended` is
     * applied at the repository via `onlyTrashed()`.
     *
     * @param  Builder<CvEloquentModel>  $query
     * @return Builder<CvEloquentModel>
     */
    public function scopeApplyFilters(Builder $query, CvFilterData $filters): Builder
    {
        return $query
            ->when($filters->search !== null, fn ($q) => $q->where(function ($w) use ($filters): void {
                $term = "%{$filters->search}%";
                $w->where('title', 'like', $term)
                    ->orWhere('original_filename', 'like', $term)
                    ->orWhere('niche', 'like', $term);
            }))
            ->when(
                $filters->niche !== null,
                fn ($q) => $q->where('niche', $filters->niche),
            )
            ->when(
                $filters->dateFrom !== null && $filters->dateTo !== null,
                fn ($q) => $q->whereBetween('created_at', [
                    CarbonImmutable::parse($filters->dateFrom)->startOfDay(),
                    CarbonImmutable::parse($filters->dateTo)->endOfDay(),
                ]),
            )
            ->when(
                $filters->dateFrom !== null && $filters->dateTo === null,
                fn ($q) => $q->where('created_at', '>=', CarbonImmutable::parse($filters->dateFrom)->startOfDay()),
            )
            ->when(
                $filters->dateTo !== null && $filters->dateFrom === null,
                fn ($q) => $q->where('created_at', '<=', CarbonImmutable::parse($filters->dateTo)->endOfDay()),
            );
    }

    /**
     * Restrict the query to the CVs owned by one user.
     *
     * A CV is personal data, so ownership — not merely holding `VIEW_CVS` — is
     * what grants access (OWASP §11, BOLA). Every read and write path in this
     * module funnels through here so no query can forget the check.
     *
     * @param  Builder<CvEloquentModel>  $query
     * @return Builder<CvEloquentModel>
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'is_primary' => 'boolean',
            'niche' => CvNiche::class,
            'file_type' => CvFileType::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'title',
                'niche',
                'is_primary',
                'file_type',
                'original_filename',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('cvs');
    }

    protected static function newFactory(): CvFactory
    {
        return CvFactory::new();
    }
}
