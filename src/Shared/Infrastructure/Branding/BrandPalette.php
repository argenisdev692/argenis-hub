<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Branding;

/**
 * The ONE brand palette every server-rendered asset draws from: AI image
 * prompts (Post / SocialMedia), the deterministic SVG roadmaps, and PDF
 * exports. Values are static design tokens, not per-tenant data.
 *
 * Single source of truth: `resources/css/globals.css`. Each constant below
 * names the exact custom property it mirrors — there is no second palette and
 * no per-module override. `resources/css/app.css` only re-exports these tokens
 * to Tailwind (`@theme { --color-*: var(--token) }`) and defines no colour of
 * its own, so keeping this class in step with globals.css keeps the whole
 * application on one palette.
 *
 * Verified against globals.css on 2026-08-31. Before this, the three constants
 * held #0a0a1a / #6366f1 / #a78bfa and the docblock pointed at `--bg-app`,
 * `--accent-primary` and `--accent-secondary` — three custom properties that
 * do not exist in globals.css. Every AI-generated image was therefore rendered
 * on a background and violet that appear nowhere in the UI.
 */
final class BrandPalette
{
    /** `--hub-bg-dark`, and the `.dark` value of `--background`. */
    public const string BACKGROUND = '#050714';

    /** `--brand-purple`, and the `.dark` value of `--primary`. */
    public const string PRIMARY_ACCENT = '#7c3aed';

    /** `--brand-indigo` — the cooler companion to the primary violet. */
    public const string SECONDARY_ACCENT = '#6366f1';

    /** `--text-main`, the `.dark` value of `--foreground`. */
    public const string TEXT_PRIMARY = '#f4f4f2';

    /** `--text-muted`, the `.dark` value of `--muted-foreground`. */
    public const string TEXT_MUTED = '#94a3b8';
}
