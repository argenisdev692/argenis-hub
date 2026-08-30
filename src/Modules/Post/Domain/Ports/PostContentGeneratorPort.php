<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Ports;

use Modules\Post\Application\DTOs\GeneratedPostContentData;
use Modules\Post\Application\DTOs\GeneratePostContentData;

/**
 * Generates a complete, SEO/EEAT/virality/ROI-scored blog draft (and
 * optionally a cover image) for a chosen topic, with layered BrandPalette
 * image prompts always returned. Internally may run up to 5 quality-loop
 * iterations. Read-only — the caller decides whether/when to persist the
 * result via CreatePostHandler / UpdatePostHandler.
 */
interface PostContentGeneratorPort
{
    public function generate(GeneratePostContentData $data, ?object $causer = null): GeneratedPostContentData;
}
