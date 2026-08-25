import type { LucideIcon } from '@lucide/vue';

/**
 * The brand hue a marketing surface is tinted with.
 *
 * Mirrors the palette hierarchy in BRAND/DESIGN_SYSTEM.md: cyan and violet
 * carry the product's primary energy, magenta marks the AI modules, and gold
 * is reserved for the money surfaces (invoices, conversion).
 */
export type BrandTone = 'cyan' | 'purple' | 'magenta' | 'indigo' | 'gold';

/** How much room a card claims in the bento grid at `lg` and up. */
export type BentoSpan = 'default' | 'wide' | 'tall';

export type MarketingLink = {
    label: string;
    /** In-page anchor (`#features`) or absolute URL. */
    href: string;
};

export type MarketingFeature = {
    id: string;
    title: string;
    description: string;
    icon: LucideIcon;
    tone: BrandTone;
    span: BentoSpan;
    /** Short capability list rendered as ticked bullets. Optional by design —
     *  only the two hero cards in the bento carry one. */
    highlights?: readonly string[];
};

export type MarketingMetric = {
    id: string;
    /** Pre-formatted for display; these are positioning claims, not live data. */
    value: string;
    label: string;
    caption: string;
};

export type MarketingStep = {
    id: string;
    title: string;
    description: string;
    icon: LucideIcon;
};

/**
 * Social channels the footer can render.
 *
 * Mirrors the keys emitted by `CompanyProfile::data()['socials']`, which are
 * derived from the `*_link` columns on `company_data`. Keys with no URL in the
 * database are filtered out server-side and never reach the page.
 */
export type SocialKey =
    'linkedin' | 'github' | 'instagram' | 'facebook' | 'tiktok' | 'twitter';

/** Company contact block shared with the landing page by `HomeController`. */
export type CompanyContact = {
    socials: Partial<Record<SocialKey, string>>;
    support_email: string | null;
};
