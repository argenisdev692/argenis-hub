<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Ai;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Post\Application\Commands\GeneratePostContentHandler;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostContentDraftData;
use Modules\Post\Application\DTOs\RenderedCoverImageData;
use Modules\Post\Domain\Enums\PostImageMode;
use Modules\Post\Domain\Ports\PostCoverImageRendererPort;
use Modules\Post\Domain\Services\PostContentQualityEvaluator;
use Modules\Post\Infrastructure\Broadcasting\PostProgressNotifier;
use Shared\Domain\Ports\StoragePort;
use Shared\Infrastructure\AI\AIClientInterface;
use Throwable;

/**
 * Everything billed by the image provider, in one place, invoked ONCE per
 * generation by {@see GeneratePostContentHandler} after the quality loop has
 * picked its winner.
 *
 * Rendering inside the loop would bill up to
 * {@see PostContentQualityEvaluator::MAX_ITERATIONS}
 * covers to keep one, and would put four discarded image calls on the critical
 * path of a request the user is waiting on. Keeping it out here is what makes
 * a rejected attempt cheap.
 *
 * Best-effort by contract (see {@see PostCoverImageRendererPort}): a provider
 * failure logs and yields a null path. By the time this runs the copy is
 * already final, and losing a completed draft because an image endpoint
 * hiccuped would be a strictly worse outcome than shipping it without artwork.
 */
final readonly class PostCoverImageRenderer implements PostCoverImageRendererPort
{
    public function __construct(
        private AIClientInterface $ai,
        private StoragePort $storage,
        private BrandImagePromptFactory $prompts,
        private PostProgressNotifier $progress,
    ) {}

    public function render(
        PostContentDraftData $draft,
        GeneratePostContentData $data,
        ?object $causer = null,
    ): RenderedCoverImageData {
        $conceptTitle = $draft->coverImageConcept['title'];
        $conceptVisual = $draft->coverImageConcept['visual'];

        // Always echoed back, in every mode — `none` still leaves the user
        // able to render the cover elsewhere with the exact palette-locked
        // text this pipeline would have used.
        $prompts = $this->prompts->layered($conceptTitle, $conceptVisual);

        if (! $data->imageMode->rendersImage()) {
            return new RenderedCoverImageData(prompts: $prompts);
        }

        $this->progress->notify($causer, 'content', 'image', 'Generating the on-brand cover image…', 90);

        $path = $this->store(match ($data->imageMode) {
            PostImageMode::Full => $this->prompts->composite($conceptTitle, $conceptVisual),
            PostImageMode::Base => $prompts['background'],
            PostImageMode::None => $prompts['background'],
        });

        return new RenderedCoverImageData(
            prompts: $prompts,
            path: $path,
            url: $this->publicUrl($path),
        );
    }

    /**
     * Renders one palette-locked prompt through the image provider and stores
     * the bytes on the configured disk. Shared by the `full` (composite) and
     * `base` (background plate) image modes — the ONLY difference between them
     * is which {@see BrandImagePromptFactory} prompt arrives here.
     */
    private function store(string $prompt): ?string
    {
        try {
            $image = $this->ai->generateImage($prompt, provider: null, size: '16:9', quality: 'high');

            $extension = str_contains($image['mime'], 'png') ? 'png' : 'jpg';

            return $this->storage->put(
                'posts/ai/'.Str::uuid7().'.'.$extension,
                base64_decode($image['base64'], true) ?: '',
                'public',
            );
        } catch (Throwable $exception) {
            Log::warning('post.render.image_failed', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    private function publicUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        try {
            return $this->storage->publicUrl($path);
        } catch (Throwable $exception) {
            Log::warning('post.render.public_url_failed', ['path' => $path, 'error' => $exception->getMessage()]);

            return null;
        }
    }
}
