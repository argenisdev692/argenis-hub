/**
 * The social channels the product knows about.
 *
 * Lives in `common/` rather than in a module because two unrelated surfaces
 * need it — the marketing footer renders the channels, the company settings
 * screen edits them — and `modules/` may not import across module boundaries.
 *
 * `SocialChannel` is aliased from the generated declarations rather than spelled
 * out again. The enum is the backend's `Modules\Company\Domain\Enums\
 * SocialChannel`, and a hand-written union beside it is precisely how a channel
 * added in PHP ends up silently missing from the UI.
 */
export type SocialChannel = Modules.Company.Domain.Enums.SocialChannel;

export type SocialChannelDescriptor = {
    key: SocialChannel;
    label: string;
    /**
     * The matching column on `company_data`, which is also the property name in
     * `UpdateCompanyData`. Typed as a template literal so the two can never
     * drift: adding a channel to the enum without a `*_link` column fails here.
     */
    field: `${SocialChannel}_link`;
    /** Placeholder showing the shape of a profile URL for this channel. */
    placeholder: string;
};

/**
 * Display order, shared by the footer and the settings form.
 *
 * Ordered by how the business actually uses them, not alphabetically — the
 * professional channels lead, the consumer ones follow.
 */
export const SOCIAL_CHANNELS: readonly SocialChannelDescriptor[] = [
    {
        key: 'linkedin',
        label: 'LinkedIn',
        field: 'linkedin_link',
        placeholder: 'https://linkedin.com/company/…',
    },
    {
        key: 'github',
        label: 'GitHub',
        field: 'github_link',
        placeholder: 'https://github.com/…',
    },
    {
        key: 'instagram',
        label: 'Instagram',
        field: 'instagram_link',
        placeholder: 'https://instagram.com/…',
    },
    {
        key: 'facebook',
        label: 'Facebook',
        field: 'facebook_link',
        placeholder: 'https://facebook.com/…',
    },
    {
        key: 'tiktok',
        label: 'TikTok',
        field: 'tiktok_link',
        placeholder: 'https://tiktok.com/@…',
    },
    {
        key: 'twitter',
        label: 'X',
        field: 'twitter_link',
        placeholder: 'https://x.com/…',
    },
] as const;

export const SOCIAL_LABELS: Readonly<Record<SocialChannel, string>> =
    Object.fromEntries(
        SOCIAL_CHANNELS.map((channel) => [channel.key, channel.label]),
    ) as Record<SocialChannel, string>;

export const SOCIAL_ORDER: readonly SocialChannel[] = SOCIAL_CHANNELS.map(
    (channel) => channel.key,
);
