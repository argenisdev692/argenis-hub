<?php

declare(strict_types=1);

namespace Modules\Blog\Application\ReadModels;

use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Response shape for the anonymous landing-page category feed. Property-level
 * authorization allowlist (OWASP §12): the public JSON is built ONLY from
 * these fields, so internal columns (`id`, `user_id`) never leak — unlike
 * serializing the Eloquent model directly and relying on `$hidden`.
 *
 * `posts` carries this category's published posts (the JSON relationship the
 * landing page renders); `postsCount` is the same set's cardinality, kept as a
 * scalar so callers that only need a badge skip iterating the array.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class BlogCategoryPublicReadModel extends Data
{
    /**
     * @param  list<PublicCategoryPostReadModel>  $posts
     */
    public function __construct(
        public string $uuid,
        public ?string $name,
        public ?string $description,
        public ?string $imageUrl,
        public int $postsCount,
        public array $posts,
    ) {}

    public static function fromModel(BlogCategoryEloquentModel $model): self
    {
        $posts = $model->relationLoaded('publishedPosts')
            ? $model->publishedPosts
                ->map(static fn (PostEloquentModel $post): PublicCategoryPostReadModel => PublicCategoryPostReadModel::fromModel($post))
                ->values()
                ->all()
            : [];

        return new self(
            uuid: $model->uuid,
            name: $model->blog_category_name,
            description: $model->blog_category_description,
            imageUrl: $model->image_url,
            postsCount: (int) ($model->published_posts_count ?? count($posts)),
            posts: $posts,
        );
    }
}
