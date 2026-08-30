<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Post\Application\DTOs\PostFilterData;
use Modules\Post\Domain\Enums\PostStatus;
use Shared\Domain\Ports\StoragePort;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Throwable;

/**
 * @property int $id
 * @property string $uuid
 * @property string $post_title
 * @property string $post_title_slug
 * @property string $post_content
 * @property string|null $post_excerpt
 * @property string|null $post_cover_image
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $meta_keywords
 * @property int|null $category_id
 * @property int|null $user_id
 * @property PostStatus $post_status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property bool $is_ai_generated
 * @property string|null $ai_provider
 * @property Carbon|null $ai_generated_at
 * @property int|null $seo_score
 * @property int|null $eeat_score
 * @property int|null $human_writing_index
 * @property int|null $ai_detection_risk
 * @property array<string, mixed>|null $ai_scores
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $cover_image_url
 * @property-read BlogCategoryEloquentModel|null $category
 * @property-read User|null $user
 *
 * @mixin \Eloquent
 */
#[Table('posts')]
#[Fillable([
    'uuid', 'post_title', 'post_title_slug', 'post_content', 'post_excerpt', 'post_cover_image',
    'meta_title', 'meta_description', 'meta_keywords', 'category_id', 'user_id', 'post_status',
    'scheduled_at', 'published_at', 'is_ai_generated', 'ai_provider', 'ai_generated_at',
    'seo_score', 'eeat_score', 'human_writing_index', 'ai_detection_risk', 'ai_scores',
])]
final class PostEloquentModel extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'category_id', 'user_id'];

    /**
     * @var list<string>
     */
    protected $appends = ['cover_image_url'];

    protected static function booted(): void
    {
        self::creating(function (PostEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * @return BelongsTo<BlogCategoryEloquentModel, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategoryEloquentModel::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Landing-page trust boundary: only `published` posts are ever reachable
     * by anonymous traffic, regardless of `scheduled_at`/`draft` state.
     *
     * @param  Builder<PostEloquentModel>  $query
     * @return Builder<PostEloquentModel>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('post_status', 'published');
    }

    /**
     * Reusable list filter (BACKEND-PHP §4.1 — single scope shared by
     * ListPostsHandler and the export controller, no duplicated `when()`
     * chains). The `suspended` status is applied at the repository via
     * `onlyTrashed()`; the remaining lifecycle values map onto `post_status`.
     *
     * @param  Builder<PostEloquentModel>  $query
     * @return Builder<PostEloquentModel>
     */
    public function scopeApplyFilters(Builder $query, PostFilterData $filters): Builder
    {
        return $query
            ->when($filters->search !== null, fn ($q) => $q->where(function ($w) use ($filters): void {
                $term = "%{$filters->search}%";
                $w->where('post_title', 'like', $term)
                    ->orWhere('post_excerpt', 'like', $term);
            }))
            ->when(
                in_array($filters->status, ['draft', 'published', 'scheduled'], true),
                fn ($q) => $q->where('post_status', $filters->status),
            )
            ->when(
                $filters->categoryUuid !== null,
                fn ($q) => $q->whereHas('category', fn ($c) => $c->where('uuid', $filters->categoryUuid)),
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
     * Permanent public URL for the stored R2 cover image, resolved through the
     * StoragePort adapter. Degrades to null on any R2 failure rather than
     * breaking the whole list/show render.
     *
     * @return Attribute<string|null, never>
     */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $key = $this->post_cover_image;

            if ($key === null || $key === '') {
                return null;
            }

            // Legacy rows may store an absolute public URL — never pass those
            // through publicUrl() or R2_URL is prepended again (double host).
            if (str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
                return $key;
            }

            try {
                return app(StoragePort::class)->publicUrl($key);
            } catch (Throwable) {
                return null;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'user_id' => 'integer',
            'post_status' => PostStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'is_ai_generated' => 'boolean',
            'ai_generated_at' => 'datetime',
            'seo_score' => 'integer',
            'eeat_score' => 'integer',
            'human_writing_index' => 'integer',
            'ai_detection_risk' => 'integer',
            'ai_scores' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'post_title',
                'post_status',
                'scheduled_at',
                'published_at',
                'category_id',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('post');
    }

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }
}
