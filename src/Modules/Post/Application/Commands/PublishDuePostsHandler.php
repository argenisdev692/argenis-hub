<?php

declare(strict_types=1);

namespace Modules\Post\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Post\Domain\Ports\PostPublicFeedCachePort;
use Modules\Post\Domain\Ports\PostRepositoryPort;

/**
 * Auto-publishes posts whose `scheduled_at` has been reached. Invoked by the
 * `posts:publish-scheduled` Artisan command, which routes/console.php runs
 * every minute — a post scheduled later today flips to `published` on the
 * next tick after its time arrives, while one scheduled for a future date
 * simply stays `scheduled` until its own tick is due.
 */
final readonly class PublishDuePostsHandler
{
    public function __construct(
        private PostRepositoryPort $posts,
        private PostPublicFeedCachePort $publicFeedCache,
    ) {}

    public function handle(): int
    {
        $due = $this->posts->dueForScheduledPublishing();

        if ($due->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($due): void {
            foreach ($due as $post) {
                $this->posts->update($post, [
                    'post_status' => 'published',
                    'published_at' => now(),
                ]);
            }
        });

        $this->publicFeedCache->flush();

        return $due->count();
    }
}
