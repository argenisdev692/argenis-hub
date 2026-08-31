<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Ports;

use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostContentDraftData;
use Modules\Post\Application\DTOs\RenderedCoverImageData;
use Modules\Post\Domain\Enums\PostImageMode;

/**
 * Everything billed by the image provider, behind one port, invoked ONCE per
 * generation — after the quality loop has already picked its winner.
 *
 * Keeping it out of {@see PostContentGeneratorPort} is what makes a rejected
 * attempt cheap: the loop may write and score five drafts, but only the
 * surviving one is ever drawn. The layered BrandPalette prompts come back in
 * every {@see PostImageMode}, including `none`,
 * where no image call is billed at all.
 *
 * Best-effort by contract: a provider failure yields a null path rather than
 * losing a finished draft because an image endpoint hiccuped.
 */
interface PostCoverImageRendererPort
{
    public function render(
        PostContentDraftData $draft,
        GeneratePostContentData $data,
        ?object $causer = null,
    ): RenderedCoverImageData;
}
