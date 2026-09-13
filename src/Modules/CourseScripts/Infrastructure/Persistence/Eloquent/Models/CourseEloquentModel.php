<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\CourseScripts\Application\DTOs\CourseFilterData;
use Modules\CourseScripts\Domain\Enums\BibleOrigin;
use Modules\CourseScripts\Domain\Enums\CourseStatus;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Concerns\GeneratesPublicUuid;
use Shared\Domain\Ports\StoragePort;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @internal Application code goes through the module's repository ports.
 *
 * Aggregate root of spec 002-course-scripts. Soft-deleted; children cascade on
 * force delete.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $title
 * @property string $language
 * @property int|null $declared_duration_minutes
 * @property int $default_video_minutes
 * @property CourseStatus $status
 * @property string|null $course_notes
 * @property array<string, mixed>|null $bible
 * @property BibleOrigin|null $bible_origin
 * @property int $bible_revision
 * @property Carbon|null $prepared_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Collection<int, CourseBlockEloquentModel> $blocks
 * @property-read Collection<int, CourseVideoEloquentModel> $videos
 * @property-read Collection<int, CourseSourceDocumentEloquentModel> $sourceDocuments
 * @property-read Collection<int, CourseResearchFindingEloquentModel> $researchFindings
 * @property-read Collection<int, CourseGenerationRunEloquentModel> $runs
 *
 * @mixin \Eloquent
 */
#[Table('courses')]
#[Fillable([
    'uuid',
    'user_id',
    'title',
    'language',
    'declared_duration_minutes',
    'default_video_minutes',
    'status',
    'course_notes',
    'bible',
    'bible_origin',
    'bible_revision',
    'prepared_at',
])]
final class CourseEloquentModel extends Model
{
    use GeneratesPublicUuid;

    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    use LogsActivity;
    use Prunable;
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id', 'user_id'];

    /** @var array<string, int> */
    protected $attributes = [
        'bible_revision' => 0,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CourseBlockEloquentModel, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(CourseBlockEloquentModel::class, 'course_id');
    }

    /**
     * @return HasMany<CourseVideoEloquentModel, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(CourseVideoEloquentModel::class, 'course_id');
    }

    /**
     * @return HasMany<CourseSourceDocumentEloquentModel, $this>
     */
    public function sourceDocuments(): HasMany
    {
        return $this->hasMany(CourseSourceDocumentEloquentModel::class, 'course_id');
    }

    /**
     * @return HasMany<CourseResearchFindingEloquentModel, $this>
     */
    public function researchFindings(): HasMany
    {
        return $this->hasMany(CourseResearchFindingEloquentModel::class, 'course_id');
    }

    /**
     * @return HasMany<CourseGenerationRunEloquentModel, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(CourseGenerationRunEloquentModel::class, 'course_id');
    }

    /**
     * Owner scoping is a security boundary (FR-53), stated explicitly rather
     * than folded into the filter scope.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Single filter source for the course list and the listing export
     * (BACKEND-PHP §5.2).
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeApplyFilters(Builder $query, CourseFilterData $filters): Builder
    {
        return $query
            ->when($filters->trashed === 'only', static fn (Builder $query): Builder => $query->onlyTrashed())
            ->when($filters->trashed === 'with', static fn (Builder $query): Builder => $query->withTrashed())
            ->when(
                $filters->search,
                static fn (Builder $query, string $search): Builder => $query->where('title', 'like', '%'.$search.'%'),
            )
            ->when(
                $filters->status,
                static fn (Builder $query, CourseStatus $status): Builder => $query->where('status', $status->value),
            )
            ->when(
                $filters->dateFrom !== null && $filters->dateTo !== null,
                static fn (Builder $query): Builder => $query->whereBetween('created_at', [
                    CarbonImmutable::parse($filters->dateFrom)->startOfDay(),
                    CarbonImmutable::parse($filters->dateTo)->endOfDay(),
                ]),
            )
            ->when(
                $filters->dateFrom !== null && $filters->dateTo === null,
                static fn (Builder $query): Builder => $query->where('created_at', '>=', CarbonImmutable::parse($filters->dateFrom)->startOfDay()),
            )
            ->when(
                $filters->dateFrom === null && $filters->dateTo !== null,
                static fn (Builder $query): Builder => $query->where('created_at', '<=', CarbonImmutable::parse($filters->dateTo)->endOfDay()),
            )
            ->orderBy($filters->safeSortField(), $filters->sortDirection())
            ->orderByDesc('id');
    }

    /**
     * Soft-deleted courses past the recovery window (A6/D6).
     *
     * @return Builder<$this>
     */
    public function prunable(): Builder
    {
        return self::onlyTrashed()->where('deleted_at', '<=', now()->subDays((int) config('course-scripts.deletion.purge_after_days', 30)));
    }

    /**
     * Private files go with the course: uploads and every rendered deliverable.
     */
    protected function pruning(): void
    {
        $storage = app(StoragePort::class);

        $paths = CourseSourceDocumentEloquentModel::query()->where('course_id', $this->id)->pluck('path')
            ->merge(
                CourseDeliverableEloquentModel::query()
                    ->whereHas('scriptVersion.video', fn (Builder $query) => $query->where('course_id', $this->id))
                    ->pluck('path'),
            );

        foreach ($paths as $path) {
            $storage->delete((string) $path);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Metadata only — never notes, bible content or file contents (FR-55).
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'bible_origin', 'bible_revision', 'prepared_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('course_scripts.course');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'declared_duration_minutes' => 'integer',
            'default_video_minutes' => 'integer',
            'status' => CourseStatus::class,
            'bible' => 'array',
            'bible_origin' => BibleOrigin::class,
            'bible_revision' => 'integer',
            'prepared_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CourseFactory
    {
        return CourseFactory::new();
    }
}
