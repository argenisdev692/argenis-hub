import { z } from 'zod';
import type { SocialMediaContentDetail } from '../types';

/**
 * The client-side mirror of `UpdateSocialMediaContentData::rules()`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety.
 *
 * ## Why `scheduled_at` is a `datetime-local` string
 *
 * The rule is `date` + `after:now`, not `date_format:Y-m-d`. A date-only value
 * resolves to midnight, so scheduling something for "today" would always fail
 * `after:now` and scheduling for tomorrow would silently mean 00:00. The form
 * therefore carries the browser's `datetime-local` value (`YYYY-MM-DDTHH:mm`),
 * which Laravel's `date` rule parses as-is. `''` is the unset sentinel — an
 * empty `<input>` yields one — and `toWritePayload()` turns it back into the
 * `null` the DTO's `?string` expects.
 *
 * `generating` is deliberately absent from the status union: the update DTO
 * does not accept it. A package still mid-generation is not something a human
 * should be able to hand-set back into that state.
 */

export const SOCIAL_MEDIA_EDITABLE_STATUSES = [
    'draft',
    'ready',
    'needs_review',
    'published',
    'scheduled',
] as const;

export const socialMediaContentFormSchema = z
    .object({
        headline: z
            .string()
            .trim()
            .min(1, 'Headline is required.')
            .max(255, 'Headline must be 255 characters or fewer.'),
        body: z.string().trim().min(1, 'Body is required.'),
        call_to_action: z
            .string()
            .trim()
            .min(1, 'Call to action is required.')
            .max(500, 'Call to action must be 500 characters or fewer.'),
        hashtags: z
            .array(
                z
                    .string()
                    .trim()
                    .max(50, 'Each hashtag is 50 characters or fewer.'),
            )
            .max(20, 'Up to 20 hashtags.'),
        status: z.enum(SOCIAL_MEDIA_EDITABLE_STATUSES),
        /** `YYYY-MM-DDTHH:mm` from a `datetime-local` input, or `''`. */
        scheduled_at: z.string(),
    })
    /**
     * `required_if:status,scheduled` and `after:now`, checked together because
     * both hang off the same field and Zod reports them on the same path.
     */
    .superRefine((values, ctx) => {
        if (values.status !== 'scheduled') {
            return;
        }

        if (values.scheduled_at === '') {
            ctx.addIssue({
                code: 'custom',
                path: ['scheduled_at'],
                message: 'Pick a date and time to schedule this package.',
            });

            return;
        }

        if (new Date(values.scheduled_at).getTime() <= Date.now()) {
            ctx.addIssue({
                code: 'custom',
                path: ['scheduled_at'],
                message: 'The scheduled time must be in the future.',
            });
        }
    });

export type SocialMediaContentFormValues = z.infer<
    typeof socialMediaContentFormSchema
>;

/** The body `PUT /social-media/{uuid}` accepts — mirrors `UpdateSocialMediaContentData`. */
export type SocialMediaContentWritePayload = {
    headline: string;
    body: string;
    call_to_action: string;
    hashtags: string[];
    status: (typeof SOCIAL_MEDIA_EDITABLE_STATUSES)[number];
    scheduled_at: string | null;
};

/**
 * An ISO8601 timestamp → the `YYYY-MM-DDTHH:mm` a `datetime-local` input
 * accepts, in the viewer's own timezone.
 *
 * Sliced off a locally-shifted ISO string rather than composed from
 * `getHours()` and friends: same result, one place to get the zero-padding
 * wrong instead of five.
 */
function toLocalDateTimeInput(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const offsetMs = date.getTimezoneOffset() * 60 * 1000;

    return new Date(date.getTime() - offsetMs).toISOString().slice(0, 16);
}

/**
 * Seeds the review form from a stored package.
 *
 * A package that has never been generated has null copy fields; they become
 * empty strings so the form's own `required` messages fire instead of Vue
 * warning about a null `value` binding.
 */
export function toSocialMediaContentFormValues(
    content: SocialMediaContentDetail,
): SocialMediaContentFormValues {
    const status = SOCIAL_MEDIA_EDITABLE_STATUSES.find(
        (value) => value === content.status,
    );

    return {
        headline: content.headline ?? '',
        body: content.body ?? '',
        call_to_action: content.call_to_action ?? '',
        hashtags: content.hashtags ?? [],
        // `generating` has no editable counterpart — fall back to the state a
        // human would move it to next.
        status: status ?? 'draft',
        scheduled_at: toLocalDateTimeInput(content.scheduled_at),
    };
}

/**
 * Projects the form onto the exact body the endpoint accepts.
 *
 * Spelled out field by field rather than looped, because the return type is
 * the write payload: a missing or misnamed key is a compile error here instead
 * of a silently dropped column at runtime.
 */
export function toWritePayload(
    values: SocialMediaContentFormValues,
): SocialMediaContentWritePayload {
    return {
        headline: values.headline.trim(),
        body: values.body.trim(),
        call_to_action: values.call_to_action.trim(),
        hashtags: values.hashtags
            .map((tag) => tag.trim())
            .filter((tag) => tag !== ''),
        status: values.status,
        // Only meaningful while scheduled; sending a stale timestamp with any
        // other status would trip `after:now` long after the user moved on.
        scheduled_at:
            values.status === 'scheduled' && values.scheduled_at !== ''
                ? values.scheduled_at
                : null,
    };
}
