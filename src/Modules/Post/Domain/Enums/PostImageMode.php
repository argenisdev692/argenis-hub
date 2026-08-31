<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Enums;

/**
 * How much of the cover artwork the AI assist step should actually render.
 *
 * All three modes always return the layered BrandPalette prompts, so the user
 * can regenerate externally regardless of the choice — the mode only decides
 * whether a billed image call is made, and what it draws.
 */
enum PostImageMode: string
{
    /** Composite cover: palette background + subject + short title. */
    case Full = 'full';

    /** Palette-locked background plate only — no subject, no title. */
    case Base = 'base';

    /** No image call at all; prompts only. */
    case None = 'none';

    public function rendersImage(): bool
    {
        return $this !== self::None;
    }
}
