<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Ai;

use Modules\SocialMedia\Domain\Enums\SocialMediaImageMode;
use Shared\Infrastructure\Branding\BrandPalette;

/**
 * Single owner of the palette-locked image prompts for this module.
 *
 * The same {@see BrandPalette} colours and the same
 * "no people / no logos / no watermark" clauses used to be spelled out inline
 * inside {@see LaravelAiSocialMediaAssistantAdapter} — once for route A and
 * once for route B. Adding the `base` image mode would have made that three
 * copies, so the strings live here and every caller (the prompt echoed back to
 * the client, the `full` render, the `base` render) reads the same source —
 * the same split Post uses for {@see BrandImagePromptFactory}.
 *
 * Colours NEVER come from the model: the agent supplies only a short title and
 * a visual concept, and this class grounds them in the real theme palette.
 */
final readonly class SocialMediaImagePromptFactory
{
    /**
     * Palette-locked background plate: no subject, no text. Doubles as the
     * `base` image-mode render prompt, so what the user sees advertised in
     * `image_prompt` is literally what gets drawn.
     */
    #[\NoDiscard]
    public function background(): string
    {
        $background = BrandPalette::BACKGROUND;
        $primaryAccent = BrandPalette::PRIMARY_ACCENT;
        $secondaryAccent = BrandPalette::SECONDARY_ACCENT;

        return <<<PROMPT
            Abstract premium dark-mode background only, no objects, no people, no text, no logo, no watermark.
            Deep navy base ({$background}) with a soft vertical cinematic gradient, faint geometric grid, subtle film grain.
            Soft glow ({$primaryAccent}) from upper-left, lilac haze ({$secondaryAccent}) lower-right, low contrast,
            wide negative space in the center for later compositing.
            Editorial tech aesthetic, 4k, photorealistic lighting, empty center stage.
            PROMPT;
    }

    /**
     * Route A — composite social graphic: palette background + subject + one
     * short title rendered together.
     */
    #[\NoDiscard]
    public function composite(string $title, string $visual): string
    {
        $background = BrandPalette::BACKGROUND;
        $primaryAccent = BrandPalette::PRIMARY_ACCENT;
        $secondaryAccent = BrandPalette::SECONDARY_ACCENT;

        return <<<PROMPT
            Premium tech social media graphic, dark mode, minimalist, high-end.
            Background: deep navy blue ({$background}) with a subtle gradient
            and soft cinematic lighting from top. Centered composition: {$visual},
            rendered as a glowing 3D glass-and-neon element in electric purple
            ({$primaryAccent}) with soft accents in ({$secondaryAccent}),
            soft rim light, subtle reflections. Below it, one short title in clean
            bold sans-serif: "{$title}". Generous negative space, thin geometric
            accent lines, faint grid texture. Aesthetic: engineered, editorial,
            Apple-keynote quality. Sharp focus, depth of field, 4k. No extra text,
            no paragraphs, no watermark.
            PROMPT;
    }

    /**
     * Route B — abstract concept art, no typography at all. Used when the
     * agent judges a title would clutter the frame (carousels, Reels covers).
     */
    #[\NoDiscard]
    public function abstract(string $visual): string
    {
        $background = BrandPalette::BACKGROUND;
        $primaryAccent = BrandPalette::PRIMARY_ACCENT;
        $secondaryAccent = BrandPalette::SECONDARY_ACCENT;

        return <<<PROMPT
            Abstract tech concept art, dark mode, minimalist, high-end cinematic.
            Deep navy blue ({$background}) background. {$visual}, rendered as a
            network of glowing interconnected nodes and data-flow lines in
            electric purple ({$primaryAccent}) with soft lilac accents
            ({$secondaryAccent}). Soft glow, volumetric lighting, depth of field,
            floating geometric shapes, subtle grid. Editorial, engineered
            aesthetic. Sharp, 4k. No text, no labels, no letters, no numbers,
            no watermark.
            PROMPT;
    }

    /**
     * The prompt that will actually be sent for this mode + route, or — for
     * {@see SocialMediaImageMode::None} — the prompt the user would need to
     * render the asset elsewhere. Route `c` is drawn deterministically in PHP
     * (SVG), so it has no model prompt; the composite text is echoed instead so
     * the field is never empty.
     */
    #[\NoDiscard]
    public function forMode(SocialMediaImageMode $mode, string $route, string $title, string $visual): string
    {
        return match (true) {
            $mode === SocialMediaImageMode::Base => $this->background(),
            $route === 'b' => $this->abstract($visual),
            default => $this->composite($title, $visual),
        };
    }
}
