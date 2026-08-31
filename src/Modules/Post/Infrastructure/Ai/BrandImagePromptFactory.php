<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Ai;

use Shared\Infrastructure\Branding\BrandPalette;

/**
 * Single owner of the palette-locked cover-image prompts.
 *
 * Previously the same {@see BrandPalette} colours and the same
 * "no people / no logos / no watermark" clauses were spelled out twice inside
 * {@see LaravelAiPostAssistantAdapter} — once for the layered prompts returned
 * to the client and once for the composite render. Adding the `base` render
 * mode would have made that three copies, so the strings live here and every
 * caller (prompt echo, `full` render, `base` render) reads the same source.
 *
 * `background()` is deliberately the exact prompt used both as the returned
 * `image_prompts.background` and as the `base` render input: what the user
 * sees advertised is literally what gets drawn.
 */
final readonly class BrandImagePromptFactory
{
    /**
     * Palette-locked background plate: no subject, no text. Doubles as the
     * `base` image-mode render prompt.
     */
    #[\NoDiscard]
    public function background(): string
    {
        $background = BrandPalette::BACKGROUND;
        $primaryAccent = BrandPalette::PRIMARY_ACCENT;
        $secondaryAccent = BrandPalette::SECONDARY_ACCENT;

        return <<<PROMPT
            Abstract premium dark-mode background only, no objects, no people, no text, no logo, no watermark.
            Deep navy base ({$background}) with soft vertical cinematic gradient, faint geometric grid, subtle film grain.
            Soft indigo glow ({$primaryAccent}) from upper-left, lilac haze ({$secondaryAccent}) lower-right, low contrast, wide negative space in center for later compositing.
            Editorial tech aesthetic, 16:9, 4k, photorealistic lighting, empty center stage.
            PROMPT;
    }

    /**
     * Cutout-ready foreground layer to composite over {@see background()}.
     */
    #[\NoDiscard]
    public function content(string $title, string $visual): string
    {
        $primaryAccent = BrandPalette::PRIMARY_ACCENT;
        $secondaryAccent = BrandPalette::SECONDARY_ACCENT;

        return <<<PROMPT
            Isolated subject on pure transparent or pure black cutout-ready background: {$visual},
            rendered as glowing 3D glass-and-neon in electric indigo ({$primaryAccent}) with soft lilac accents ({$secondaryAccent}),
            rim light, subtle reflections, sharp focus, depth of field.
            Optional single short title below in clean bold sans-serif: "{$title}".
            No paragraphs, no extra UI, no watermark, centered, Apple-keynote quality, 16:9.
            PROMPT;
    }

    /**
     * One-shot cover — background, subject and title rendered together. Used by
     * the `full` image mode.
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
     * Both layers, as echoed back to the client in every image mode.
     *
     * @return array{background: string, content: string}
     */
    #[\NoDiscard]
    public function layered(string $title, string $visual): array
    {
        return [
            'background' => $this->background(),
            'content' => $this->content($title, $visual),
        ];
    }
}
