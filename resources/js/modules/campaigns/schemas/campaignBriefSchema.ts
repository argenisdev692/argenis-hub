import { z } from 'zod';
import type {
    GenerateCampaignPayload,
    SuggestCampaignTopicsPayload,
} from '../types';

/**
 * The client-side mirror of `SuggestCampaignTopicsData::rules()` and
 * `GenerateCampaignData::rules()` — one schema for both, because the wizard is
 * one form.
 *
 * Step 1 reads a subset of these values; step 2 reads all of them. Splitting
 * them into two schemas would mean two sources of truth for `provider`,
 * `language`, `niche`, `audience` and the four geo fields, and the first field
 * to drift would be the one nobody re-checked.
 *
 * `''` is the unset sentinel for every optional text axis (an empty `<input>`
 * yields one); the two projections below turn those back into the `null` the
 * DTOs' `?string` parameters expect.
 *
 * `business_goal` is optional on the server for step 1 and required for step 2.
 * It is required here, because the wizard asks for it up front either way — a
 * schema that permitted the looser case would only let the user reach the
 * generate button with a value the server would then reject.
 */

export const CAMPAIGN_PROVIDERS = ['openai', 'anthropic', 'gemini'] as const;
export const CAMPAIGN_LANGUAGES = ['es', 'en', 'pt-PT'] as const;
export const CAMPAIGN_BUSINESS_GOALS = [
    'awareness',
    'engagement',
    'leads',
    'sales',
    'retention',
] as const;
export const CAMPAIGN_BRAND_VOICES = [
    'professional',
    'conversational',
    'trendy',
    'inspirational',
    'humorous',
] as const;
export const CAMPAIGN_FUNNEL_STAGES = [
    'tofu',
    'mofu',
    'bofu',
    'loyalty',
] as const;
export const CAMPAIGN_PLATFORMS = ['facebook', 'instagram', 'both'] as const;
export const CAMPAIGN_AD_FORMATS = [
    'feed',
    'story',
    'reel',
    'carousel',
    'lead_form',
] as const;

export const campaignBriefSchema = z.object({
    provider: z.enum(CAMPAIGN_PROVIDERS),
    language: z.enum(CAMPAIGN_LANGUAGES),
    business_goal: z.enum(CAMPAIGN_BUSINESS_GOALS),
    brand_voice: z.enum(CAMPAIGN_BRAND_VOICES),
    funnel_stage: z.enum(CAMPAIGN_FUNNEL_STAGES),
    platform: z.enum(CAMPAIGN_PLATFORMS),
    ad_format: z.enum(CAMPAIGN_AD_FORMATS),
    topic: z
        .string()
        .trim()
        .min(1, 'Pick a suggested angle or write your own.')
        .max(255, 'Topic must be 255 characters or fewer.'),
    niche: z.string().trim().max(255, 'Niche must be 255 characters or fewer.'),
    audience: z
        .string()
        .trim()
        .max(255, 'Audience must be 255 characters or fewer.'),
    angle: z.string().trim().max(500, 'Angle must be 500 characters or fewer.'),
    hook: z.string().trim().max(500, 'Hook must be 500 characters or fewer.'),
    key_trend: z
        .string()
        .trim()
        .max(255, 'Key trend must be 255 characters or fewer.'),
    // The four geo fields feed Tavily's local-trend research and the Meta
    // targeting suggestions. `location` is the free-form catch-all for anything
    // the three structured fields cannot express ("the Algarve", "DACH").
    city: z.string().trim().max(120, 'City must be 120 characters or fewer.'),
    state: z.string().trim().max(120, 'State must be 120 characters or fewer.'),
    country: z
        .string()
        .trim()
        .max(120, 'Country must be 120 characters or fewer.'),
    location: z
        .string()
        .trim()
        .max(255, 'Location must be 255 characters or fewer.'),
    generate_images: z.boolean(),
});

export type CampaignBriefValues = z.infer<typeof campaignBriefSchema>;

export function emptyCampaignBrief(): CampaignBriefValues {
    return {
        provider: 'openai',
        language: 'en',
        // `leads` rather than `awareness`: this module exists for Meta lead-gen
        // campaigns, and `SuggestCampaignTopicsData` asks the agent for
        // lead-gen angles by default.
        business_goal: 'leads',
        brand_voice: 'professional',
        funnel_stage: 'tofu',
        platform: 'both',
        ad_format: 'feed',
        topic: '',
        niche: '',
        audience: '',
        angle: '',
        hook: '',
        key_trend: '',
        city: '',
        state: '',
        country: '',
        location: '',
        // Matches the DTO default. Turning it off is an explicit cost saving on
        // a draft, not a surprise.
        generate_images: true,
    };
}

/** `''` → `null`, so the server's `nullable` rules see an absent value. */
function orNull(value: string): string | null {
    return value === '' ? null : value;
}

/** Step 1's body — `POST /campaigns/ai/suggest-topics`. */
export function toSuggestTopicsPayload(
    values: CampaignBriefValues,
): SuggestCampaignTopicsPayload {
    return {
        provider: values.provider,
        language: values.language,
        niche: orNull(values.niche),
        audience: orNull(values.audience),
        business_goal: values.business_goal,
        city: orNull(values.city),
        state: orNull(values.state),
        country: orNull(values.country),
        location: orNull(values.location),
    };
}

/**
 * Step 2's body — `POST /campaigns/ai/generate-campaign`.
 *
 * Spelled out field by field rather than spread, because the return type is the
 * wire payload: a missing or misnamed key is a compile error here instead of a
 * 422 the user meets after the provider call is already billed.
 */
export function toGenerateCampaignPayload(
    values: CampaignBriefValues,
): GenerateCampaignPayload {
    return {
        topic: values.topic.trim(),
        provider: values.provider,
        language: values.language,
        business_goal: values.business_goal,
        brand_voice: values.brand_voice,
        funnel_stage: values.funnel_stage,
        platform: values.platform,
        ad_format: values.ad_format,
        angle: orNull(values.angle),
        hook: orNull(values.hook),
        key_trend: orNull(values.key_trend),
        niche: orNull(values.niche),
        audience: orNull(values.audience),
        generate_images: values.generate_images,
        city: orNull(values.city),
        state: orNull(values.state),
        country: orNull(values.country),
        location: orNull(values.location),
    };
}
