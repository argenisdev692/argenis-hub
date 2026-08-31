import { z } from 'zod';
import type {
    GenerateSocialMediaContentPayload,
    SuggestSocialMediaTopicsPayload,
} from '../types';

/**
 * The client-side mirror of `SuggestSocialMediaTopicsData::rules()` and
 * `GenerateSocialMediaContentData::rules()` — one schema for both, because the
 * wizard is one form.
 *
 * Step 1 reads a subset of these values; step 2 reads all of them. Splitting
 * them into two schemas would mean two sources of truth for `provider`,
 * `language`, `niche` and `audience`, and the first field to drift would be the
 * one nobody re-checked.
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

export const PROVIDERS = ['openai', 'anthropic', 'gemini'] as const;
export const LANGUAGES = ['es', 'en', 'pt-PT'] as const;
export const BUSINESS_GOALS = [
    'awareness',
    'engagement',
    'viral',
    'leads',
    'sales',
    'community',
] as const;
export const BRAND_VOICES = [
    'professional',
    'conversational',
    'trendy',
    'inspirational',
    'humorous',
] as const;
export const FUNNEL_STAGES = ['tofu', 'mofu', 'bofu'] as const;
export const IMAGE_MODES = ['full', 'base', 'none'] as const;

export const socialMediaBriefSchema = z.object({
    provider: z.enum(PROVIDERS),
    language: z.enum(LANGUAGES),
    business_goal: z.enum(BUSINESS_GOALS),
    brand_voice: z.enum(BRAND_VOICES),
    funnel_stage: z.enum(FUNNEL_STAGES),
    topic: z
        .string()
        .trim()
        .min(1, 'Pick a suggested topic or write your own.')
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
    image_mode: z.enum(IMAGE_MODES),
    generate_voiceover: z.boolean(),
});

export type SocialMediaBriefValues = z.infer<typeof socialMediaBriefSchema>;

export function emptySocialMediaBrief(): SocialMediaBriefValues {
    return {
        provider: 'openai',
        language: 'en',
        business_goal: 'engagement',
        brand_voice: 'conversational',
        funnel_stage: 'tofu',
        topic: '',
        niche: '',
        audience: '',
        angle: '',
        hook: '',
        key_trend: '',
        // `full` matches the DTO default: composite cover + per-platform
        // artwork. The cheaper modes are an explicit opt-out, not a surprise.
        image_mode: 'full',
        generate_voiceover: true,
    };
}

/** `''` → `null`, so the server's `nullable` rules see an absent value. */
function orNull(value: string): string | null {
    return value === '' ? null : value;
}

/** Step 1's body — `POST /social-media/ai/suggest-topics`. */
export function toSuggestTopicsPayload(
    values: SocialMediaBriefValues,
): SuggestSocialMediaTopicsPayload {
    return {
        provider: values.provider,
        language: values.language,
        niche: orNull(values.niche),
        audience: orNull(values.audience),
        business_goal: values.business_goal,
    };
}

/**
 * Step 2's body — `POST /social-media/ai/generate-content`.
 *
 * Spelled out field by field rather than spread, because the return type is the
 * wire payload: a missing or misnamed key is a compile error here instead of a
 * 422 the user meets after the provider call is already billed.
 */
export function toGenerateContentPayload(
    values: SocialMediaBriefValues,
): GenerateSocialMediaContentPayload {
    return {
        topic: values.topic.trim(),
        provider: values.provider,
        language: values.language,
        business_goal: values.business_goal,
        brand_voice: values.brand_voice,
        funnel_stage: values.funnel_stage,
        angle: orNull(values.angle),
        hook: orNull(values.hook),
        key_trend: orNull(values.key_trend),
        niche: orNull(values.niche),
        audience: orNull(values.audience),
        image_mode: values.image_mode,
        generate_voiceover: values.generate_voiceover,
    };
}
