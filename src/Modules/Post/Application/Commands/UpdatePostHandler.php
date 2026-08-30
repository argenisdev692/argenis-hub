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
 * Updates an existing post. The slug is only re-derived when the title
 * actually changed (stable permalinks). A new image replaces the previous R2
 * object (the old key is deleted after commit); `published_at` is stamped the
 * first time a post transitions into `published` and never overwritten
 * afterwards. Authorization (permission:UPDATE_POSTS) is enforced at the route.
 */
final readonly class UpdatePostHandler
{
    public function __construct(
        private PostRepositoryPort $posts,
        private StoragePort $storage,
        private PostPublicFeedCachePort $publicFeedCache,
    ) {}

    public function handle(PostEloquentModel $post, PostData $data): PostEloquentModel
    {
        $attributes = [
            'post_content' => $data->content,
            'post_excerpt' => $data->excerpt,
            'meta_title' => $data->metaTitle,
            'meta_description' => $data->metaDescription,
            'meta_keywords' => $data->metaKeywords,
            'category_uuid' => $data->categoryUuid,
            'post_status' => $data->status,
            'scheduled_at' => $data->scheduledAt,
            'is_ai_generated' => $data->isAiGenerated,
            'ai_provider' => $data->aiProvider,
            'seo_score' => $data->seoScore,
            'eeat_score' => $data->eeatScore,
            'human_writing_index' => $data->humanWritingIndex,
            'ai_detection_risk' => $data->aiDetectionRisk,
            'ai_scores' => $data->aiScores,
        ];

        if ($data->title !== $post->post_title) {
            $attributes['post_title'] = $data->title;
            $attributes['post_title_slug'] = $this->uniqueSlug(Str::slug($data->title), $post->uuid);
        }

        if ($data->isAiGenerated) {
            $attributes['ai_generated_at'] = now();
        }

        if ($data->status === 'published' && $post->published_at === null) {
            $attributes['published_at'] = now();
        }

        // Flush if the post was published before OR is published after this
        // update — covers unpublish, publish, and content/category edits made
        // while it stays published (BACKEND-PHP §5 Cache Management).
        $wasOrIsPublished = $post->post_status->value === 'published' || $data->status === 'published';

        $previous = null;

        if ($data->coverImage !== null) {
            $previous = $post->post_cover_image;
            // Upload before the transaction — object storage is not transactional.
            $attributes['post_cover_image'] = $this->storage->putFile('posts', $data->coverImage, 'public');
        } elseif ($data->coverImagePath !== null) {
            $normalized = $this->normalizeCoverPath($data->coverImagePath);
            if ($normalized !== null && $normalized !== $post->post_cover_image) {
                $attributes['post_cover_image'] = $normalized;
            }
        }

        $updated = DB::transaction(fn () => $this->posts->update($post, $attributes));

        // Delete the superseded object only after the write commits, so a failed
        // update never orphans the row from its still-referenced image.
        if (is_string($previous) && $previous !== '' && $previous !== $updated->post_cover_image) {
            $this->storage->delete($previous);
        }

        if ($wasOrIsPublished) {
            $this->publicFeedCache->flush();
        }

        return $updated;
    }

    /**
     * Persist object keys only. Absolute public URLs are reduced to their path
     * so cover_image_url is not double-prefixed with R2_URL.
     */
    private function normalizeCoverPath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'https://') || str_starts_with($path, 'http://')) {
            $key = ltrim(rawurldecode((string) parse_url($path, PHP_URL_PATH)), '/');

            return $key !== '' ? $key : null;
        }

        return ltrim($path, '/');
    }

    private function uniqueSlug(string $base, string $ignoreUuid): string
    {
        $slug = $base;
        $suffix = 1;

        while ($this->posts->slugExists($slug, $ignoreUuid)) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
