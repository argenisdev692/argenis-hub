<?php

declare(strict_types=1);

namespace Modules\Blog\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\BlogCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Blog\Application\DTOs\BlogCategoryFilterData;
use Modules\Post\Domain\Enums\PostStatus;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Shared\Domain\Ports\StoragePort;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Throwable;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $blog_category_name
 * @property string|null $blog_category_description
 * @property string|null $blog_category_image
 * @property int $user_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $image_url
 * @property-read User $user
 *
 * @mixin \Eloquent
 */
#[Table('blog_categories')]
#[Fillable(['uuid', 'blog_category_name', 'blog_category_description', 'blog_category_image', 'user_id'])]
final class BlogCategoryEloquentModel extends Model
{
    /** @use HasFactory<BlogCategoryFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $hidden = ['id'];

    /**
     * Resolve the public image URL alongside the raw column so the frontend
     * never concatenates R2 keys by hand.
     *
     * @var list<string>
     */
    protected $appends = ['image_url'];

    protected static function booted(): void
    {
        self::creating(function (BlogCategoryEloquentModel $model): void {
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
     * Posts filed under this category.
     *
     * @return HasMany<PostEloquentModel, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(PostEloquentModel::class, 'category_id');
    }

    /**
     * Published posts only — used by the public landing-page category feed count.
     *
     * @return HasMany<PostEloquentModel, $this>
     */
    public function publishedPosts(): HasMany
    {
        return $this->hasMany(PostEloquentModel::class, 'category_id')
            ->where('post_status', PostStatus::Published->value);
    }

    /**
     * Reusable list filter (BACKEND-PHP §4.1 — single scope shared by
     * ListBlogCategoriesHandler, no duplicated `when()` chains). The `suspended`
     * status is applied at the repository via `onlyTrashed()`.
     *
     * @param  Builder<BlogCategoryEloquentModel>  $query
     * @return Builder<BlogCategoryEloquentModel>
     */
    public function scopeApplyFilters(Builder $query, BlogCategoryFilterData $filters): Builder
    {
        return $query
            ->when($filters->search !== null, fn ($q) => $q->where(function ($w) use ($filters): void {
                $term = "%{$filters->search}%";
                $w->where('blog_category_name', 'like', $term)
                    ->orWhere('blog_category_description', 'like', $term);
            }))
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
     * Permanent public URL for the stored R2 object key, resolved through the
     * StoragePort adapter. Null when no image is set; degrades to null on any R2
     * failure rather than breaking the whole list render.
     *
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $key = $this->blog_category_image;

            if ($key === null || $key === '') {
                return null;
            }

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
            'user_id' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'blog_category_name',
                'blog_category_description',
                'blog_category_image',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('blog.category');
    }

    protected static function newFactory(): BlogCategoryFactory
    {
        return BlogCategoryFactory::new();
    }
}
