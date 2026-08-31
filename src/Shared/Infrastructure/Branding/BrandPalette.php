<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Branding;

/**
 * The ONE brand palette every server-rendered asset draws from: AI image
 * prompts (Post / SocialMedia), the deterministic SVG roadmaps, transactional
 * email layouts, and PDF exports. Values are static design tokens, not
 * per-tenant data.
 *
 * Single source of truth: `resources/css/globals.css`. Each constant below
 * names the exact custom property it mirrors — there is no second palette and
 * no per-module override. `resources/css/app.css` only re-exports these tokens
 * to Tailwind (`@theme { --color-*: var(--token) }`) and defines no colour of
 * its own, so keeping this class in step with globals.css keeps the whole
 * application on one palette.
 *
 * Why a PHP mirror exists at all: none of the consumers above can read a CSS
 * custom property. Email clients do not support `var()`, DomPDF resolves no
 * cascade, and an image prompt is a string sent to a provider. The mirror is
 * the price of that; a SECOND mirror is not, which is why
 * `resources/views/emails/layout.blade.php` reads these constants rather than
 * keeping its own copy of the hexes.
 *
 * Verified against globals.css on 2026-08-31. Before this, BACKGROUND /
 * PRIMARY_ACCENT / SECONDARY_ACCENT held #0a0a1a / #6366f1 / #a78bfa and the
 * docblock pointed at `--bg-app`, `--accent-primary` and `--accent-secondary`
 * — three custom properties that do not exist in globals.css. Every
 * AI-generated image was therefore rendered on a background and violet that
 * appear nowhere in the UI. Assert through these constants, never against a
 * hex literal, or the same drift passes its tests again.
 */
final class BrandPalette
{
    /** `--hub-bg-dark`, and the `.dark` value of `--background`. */
    public const string BACKGROUND = '#050714';

    /** `--hub-bg-surface`, and the `.dark` value of `--card`. */
    public const string SURFACE = '#08081f';

    /** `--hub-bg-card`, and the `.dark` value of `--popover` / `--secondary`. */
    public const string ELEVATED_SURFACE = '#131b3a';

    /** `--hub-border`, and the `.dark` value of `--border`. */
    public const string BORDER = '#1e2a4a';

    /** `--brand-purple`, and the `.dark` value of `--primary`. */
    public const string PRIMARY_ACCENT = '#7c3aed';

    /** `--brand-purple-soft` — the lightened violet that stays legible on `PRIMARY_ACCENT`. */
    public const string PRIMARY_ACCENT_SOFT = '#a78bfa';

    /** `--brand-indigo` — the cooler companion to the primary violet. */
    public const string SECONDARY_ACCENT = '#6366f1';

    /** `--brand-cyan` — the far end of the hero gradient. */
    public const string CYAN_ACCENT = '#06b6d4';

    /** `--text-main`, the `.dark` value of `--foreground`. */
    public const string TEXT_PRIMARY = '#f4f4f2';

    /** `--text-secondary` — body copy that recedes from a heading without reading as disabled. */
    public const string TEXT_SECONDARY = '#cbd2e0';

    /** `--text-muted`, the `.dark` value of `--muted-foreground`. */
    public const string TEXT_MUTED = '#94a3b8';

    /**
     * `--gradient-tech`, composed from the two constants it is made of rather
     * than restated as a literal — the CSS token and this string cannot drift
     * apart in the endpoints while they share the same source colours.
     */
    #[\NoDiscard]
    public static function gradientTech(): string
    {
        return 'linear-gradient(135deg, '.self::PRIMARY_ACCENT.' 0%, '.self::CYAN_ACCENT.' 100%)';
    }
}
