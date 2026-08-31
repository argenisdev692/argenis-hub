<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Ports;

use Modules\Post\Application\DTOs\GeneratedPostContentData;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Domain\Enums\PostImageMode;

/**
 * Generates a complete, SEO/EEAT/virality/ROI-scored blog draft for a chosen
 * topic. The caller's {@see PostImageMode} decides
 * how much cover artwork is rendered against the brand palette — the full
 * composite, the background plate alone, or nothing — while the layered
 * BrandPalette prompts come back in every mode. Internally may run up to 5
 * quality-loop iterations. Read-only — the caller decides whether/when to
 * persist the result via CreatePostHandler / UpdatePostHandler.
 */
interface PostContentGeneratorPort
{
    public function generate(GeneratePostContentData $data, ?object $causer = null): GeneratedPostContentData;
}
