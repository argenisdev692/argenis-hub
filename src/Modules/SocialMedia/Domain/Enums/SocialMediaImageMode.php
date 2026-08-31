<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Enums;

use Modules\Post\Domain\Enums\PostImageMode;

/**
 * How much of the cover / per-platform artwork the generation loop actually
 * renders. Same three-way contract as Post's {@see PostImageMode}, extended to
 * the 5-platform fan-out: one choice applies to the cover AND every platform
 * variant, because mixing modes per platform buys nothing and doubles the
 * billing surface.
 *
 * All three modes always return the palette-locked prompts on the DTO, so the
 * user can render externally regardless of the choice — the mode only decides
 * whether a billed image call is made, and what it draws.
 *
 * `Full` is expensive: 6 images (cover + 5 platforms) per accepted attempt.
 * `Base` renders the palette background plate only — the plate a designer
 * composites a title over in Figma/Canva. `None` bills nothing.
 */
enum SocialMediaImageMode: string
{
    /** Composite: palette background + subject + short title (route A/B/C). */
    case Full = 'full';

    /** Palette-locked background plate only — no subject, no title. */
    case Base = 'base';

    /** No image call at all; prompts only. */
    case None = 'none';

    public function rendersImage(): bool
    {
        return $this !== self::None;
    }

    /**
     * `base` deliberately ignores the agent's A/B/C route: a background plate
     * has no subject to place and no labels to draw, so the SVG roadmap route
     * would render an empty diagram.
     */
    public function honorsImageRoute(): bool
    {
        return $this === self::Full;
    }
}
