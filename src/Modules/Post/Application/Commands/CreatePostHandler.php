<?php

declare(strict_types=1);

namespace Modules\Post\Application\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Post\Application\DTOs\PostData;
use Modules\Post\Domain\Ports\PostPublicFeedCachePort;
use Modules\Post\Domain\Ports\PostRepositoryPort;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Shared\Domain\Ports\StoragePort;

/**
 * Persists a new post. `post_title_slug` is always derived server-side from the
 * title (never user-typed) and de-duplicated via {@see self::uniqueSlug()}. A
 * manual upload wins over an AI-generated cover path when both are present.
 * Authorization (permission:CREATE_POSTS) is enforced at the route.
 */
final readonly class CreatePostHandler
{
    public function __construct(
        private PostRepositoryPort $posts,
        private StoragePort $storage,
        private PostPublicFeedCachePort $publicFeedCache,
    ) {}

    #[\NoDiscard]
    public function handle(PostData $data, int $userId): PostEloquentModel
    {
        // Upload happens before the transaction — object storage is not
        // transactional and must never run inside the DB unit-of-work.
        $coverImagePath = $data->coverImage !== null
            ? $this->storage->putFile('posts', $data->coverImage, 'public')
            : $this->normalizeCoverPath($data->coverImagePath);

        $slug = $this->uniqueSlug(Str::slug($data->title));
        $isPublished = $data->status === 'published';

        $post = DB::transaction(fn () => $this->posts->create([
            'post_title' => $data->title,
            'post_title_slug' => $slug,
            'post_content' => $data->content,
            'post_excerpt' => $data->excerpt,
            'post_cover_image' => $coverImagePath,
            'meta_title' => $data->metaTitle,
            'meta_description' => $data->metaDescription,
            'meta_keywords' => $data->metaKeywords,
            'category_uuid' => $data->categoryUuid,
            'user_id' => $userId,
            'post_status' => $data->status,
            'scheduled_at' => $data->scheduledAt,
            'published_at' => $isPublished ? now() : null,
            'is_ai_generated' => $data->isAiGenerated,
            'ai_provider' => $data->aiProvider,
            'ai_generated_at' => $data->isAiGenerated ? now() : null,
            'seo_score' => $data->seoScore,
            'eeat_score' => $data->eeatScore,
            'human_writing_index' => $data->humanWritingIndex,
            'ai_detection_risk' => $data->aiDetectionRisk,
            'ai_scores' => $data->aiScores,
        ]));

        if ($isPublished) {
            $this->publicFeedCache->flush();
        }

        return $post;
    }

    /**
     * Persist object keys only. Absolute public URLs (legacy / mistaken client
     * payloads) are reduced to their path so cover_image_url is not double-prefixed.
     */
    private function normalizeCoverPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return $path;
        }

        if (str_starts_with($path, 'https://') || str_starts_with($path, 'http://')) {
            return ltrim(rawurldecode((string) parse_url($path, PHP_URL_PATH)), '/');
        }

        return ltrim($path, '/');
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 1;

        while ($this->posts->slugExists($slug)) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
