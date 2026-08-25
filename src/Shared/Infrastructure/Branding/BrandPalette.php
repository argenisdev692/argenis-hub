<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Branding;

/**
 * App-wide dark-mode brand palette used to ground AI-generated image prompts
 * (Post / SocialMedia layered background + content prompts, and composite
 * Imagen covers) in real, on-brand colors instead of whatever the model
 * invents. Values are static design tokens, not per-tenant data — kept in
 * sync by hand with the `.dark` custom properties in `resources/css/globals.css`
 * (`--bg-app`, `--accent-primary`, `--accent-secondary`).
 */
final class BrandPalette
{
    public const string BACKGROUND = '#0a0a1a';

    public const string PRIMARY_ACCENT = '#6366f1';

    public const string SECONDARY_ACCENT = '#a78bfa';
}
