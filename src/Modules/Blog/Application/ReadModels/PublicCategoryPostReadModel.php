<?php

declare(strict_types=1);

namespace Modules\Blog\Application\ReadModels;

use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Post teaser embedded in the anonymous landing-page category feed
 * ({@see BlogCategoryPublicReadModel}). Property-level authorization allowlist
 * (OWASP §12): only these published-post fields are exposed — internal columns
 * (`id`, `category_id`, `user_id`) and AI diagnostics never leak. Kept
 * deliberately lean (no `content`) to bound the feed payload (OWASP API4).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PublicCategoryPostReadModel extends Data
{
    public function __construct(
        public string $uuid,
        public string $title,
        public string $slug,
        public ?string $excerpt,
        public ?string $coverImageUrl,
        public ?string $publishedAt,
    ) {}

    public static function fromModel(PostEloquentModel $model): self
    {
        return new self(
            uuid: $model->uuid,
            title: $model->post_title,
            slug: $model->post_title_slug,
            excerpt: $model->post_excerpt,
            coverImageUrl: $model->cover_image_url,
            publishedAt: $model->published_at?->toIso8601String(),
        );
    }
}
