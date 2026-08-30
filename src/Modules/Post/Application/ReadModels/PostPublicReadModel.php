<?php

declare(strict_types=1);

namespace Modules\Post\Application\ReadModels;

use Modules\Post\Application\Queries\GetPublicPostHandler;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Response shape for the anonymous landing-page post feed AND the single-post
 * detail view. Property-level authorization allowlist (OWASP §12): the public
 * JSON is built ONLY from these fields, so internal columns (`id`,
 * `category_id`, `user_id`) and AI-internal diagnostics never leak.
 *
 * `content` stays `null` on the list endpoint (bandwidth — OWASP API4) and is
 * only populated by {@see GetPublicPostHandler}
 * for the single-post detail page.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class PostPublicReadModel extends Data
{
    public function __construct(
        public string $uuid,
        public string $title,
        public string $slug,
        public ?string $excerpt,
        public ?string $content,
        public ?string $coverImageUrl,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $metaKeywords,
        public ?string $categoryUuid,
        public ?string $categoryName,
        public ?string $publishedAt,
    ) {}

    public static function fromModel(PostEloquentModel $model, bool $includeContent = false): self
    {
        return new self(
            uuid: $model->uuid,
            title: $model->post_title,
            slug: $model->post_title_slug,
            excerpt: $model->post_excerpt,
            content: $includeContent ? $model->post_content : null,
            coverImageUrl: $model->cover_image_url,
            metaTitle: $model->meta_title,
            metaDescription: $model->meta_description,
            metaKeywords: $model->meta_keywords,
            categoryUuid: $model->category?->uuid,
            categoryName: $model->category?->blog_category_name,
            publishedAt: $model->published_at?->toIso8601String(),
        );
    }
}
