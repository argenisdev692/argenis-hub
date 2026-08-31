import { z } from 'zod';
import type { PostDetail, PostWritePayload } from '../types';

/**
 * The client-side mirror of `PostData::rules()`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety.
 *
 * ## Field-shape notes
 *
 * - `content` is HTML from the Tiptap editor. It is checked for *text*, not
 *   length: an untouched editor still serializes as `<p></p>`, so a plain
 *   `.min(1)` would call an empty document valid.
 * - `cover_image` is a one-element `File[]` rather than a `File | null`, so it
 *   drops straight into `FileDropzone`'s `File[]` model; the wire boundary in
 *   `toWritePayload()` is where it collapses back to a single file.
 * - Optional text fields are plain `''` rather than `null` in the form —
 *   an `<input>` cannot express null — and are normalized at that same
 *   boundary.
 * - The `ai_*` fields never appear in the markup. They are provenance the AI
 *   panel writes and the form carries through untouched, which is why they are
 *   modelled but not validated beyond their type.
 */

const optionalText = (max: number, label: string) =>
    z.string().trim().max(max, `${label} must be ${max} characters or fewer.`);

const score = z.number().int().min(0).max(100).nullable();

/** 4 MB and three formats — `PostData::rules()`' `max:4096` + `mimes:`. */
const MAX_COVER_BYTES = 4 * 1024 * 1024;
const COVER_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * True when the editor's HTML carries actual prose. Tags are stripped rather
 * than parsed because the only question is "is there text", and a DOM parse
 * would drag a browser dependency into a schema that also runs under test.
 */
function htmlHasText(html: string): boolean {
    return (
        html
            .replace(/<[^>]*>/g, ' ')
            .replace(/&nbsp;/gi, ' ')
            .trim().length > 0
    );
}

export const postFormSchema = z
    .object({
        title: z
            .string()
            .trim()
            .min(1, 'Title is required.')
            .max(255, 'Title must be 255 characters or fewer.'),
        content: z
            .string()
            .refine(htmlHasText, { message: 'Content is required.' }),
        excerpt: optionalText(500, 'Excerpt'),
        cover_image: z
            .array(
                z
                    .instanceof(File)
                    .refine((file) => file.size <= MAX_COVER_BYTES, {
                        message: 'The cover image must be 4 MB or smaller.',
                    })
                    .refine((file) => COVER_MIME_TYPES.includes(file.type), {
                        message: 'The cover must be a JPG, PNG or WEBP image.',
                    }),
            )
            .max(1, 'Only one cover image.'),
        cover_image_path: optionalText(2048, 'Cover image path'),
        meta_title: optionalText(255, 'Meta title'),
        meta_description: optionalText(500, 'Meta description'),
        meta_keywords: optionalText(255, 'Meta keywords'),
        category_uuid: z.string().nullable(),
        status: z.enum(['draft', 'published', 'scheduled']),
        /** `datetime-local` value — `YYYY-MM-DDTHH:mm`. */
        scheduled_at: z.string().nullable(),
        is_ai_generated: z.boolean(),
        ai_provider: z.string().nullable(),
        seo_score: score,
        eeat_score: score,
        human_writing_index: score,
        ai_detection_risk: score,
        ai_scores: z.record(z.string(), z.unknown()).nullable(),
    })
    .superRefine((values, ctx) => {
        if (values.status !== 'scheduled') {
            return;
        }

        // Mirrors `required_if:status,scheduled` + `after:now`. Checked here
        // rather than on the field so the message appears the moment the user
        // picks "Scheduled", instead of only after they touch the date.
        if (!values.scheduled_at) {
            ctx.addIssue({
                code: 'custom',
                path: ['scheduled_at'],
                message: 'Pick a publish date to schedule this post.',
            });

            return;
        }

        if (new Date(values.scheduled_at).getTime() <= Date.now()) {
            ctx.addIssue({
                code: 'custom',
                path: ['scheduled_at'],
                message: 'The publish date must be in the future.',
            });
        }
    });

export type PostFormValues = z.infer<typeof postFormSchema>;

/** The three lifecycle values a form may set — `suspended` is not one of them. */
export const POST_STATUS_VALUES = [
    'draft',
    'published',
    'scheduled',
] as const satisfies readonly PostFormValues['status'][];

/**
 * Narrows what a `<Select>` hands back. reka-ui types its model as
 * `AcceptableValue`, so a guard is what stops that widening into the form.
 */
export function isPostStatus(
    value: unknown,
): value is PostFormValues['status'] {
    return POST_STATUS_VALUES.some((status) => status === value);
}

export function emptyPostFormValues(): PostFormValues {
    return {
        title: '',
        content: '',
        excerpt: '',
        cover_image: [],
        cover_image_path: '',
        meta_title: '',
        meta_description: '',
        meta_keywords: '',
        category_uuid: null,
        status: 'draft',
        scheduled_at: null,
        is_ai_generated: false,
        ai_provider: null,
        seo_score: null,
        eeat_score: null,
        human_writing_index: null,
        ai_detection_risk: null,
        ai_scores: null,
    };
}

/**
 * ISO-8601 → the `YYYY-MM-DDTHH:mm` a `datetime-local` input accepts.
 *
 * Sliced off the *local* clock rather than `toISOString()`, which would shift a
 * scheduled time by the viewer's UTC offset — the one bug this conversion
 * exists to avoid.
 */
function toDateTimeLocal(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const pad = (value: number) => String(value).padStart(2, '0');

    return (
        `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
        `T${pad(date.getHours())}:${pad(date.getMinutes())}`
    );
}

export function toPostFormValues(post: PostDetail): PostFormValues {
    return {
        title: post.post_title,
        content: post.post_content,
        excerpt: post.post_excerpt ?? '',
        // An existing cover is an R2 key, not a file — it stays in
        // `cover_image_path` and the dropzone starts empty, so re-saving
        // without touching it keeps the image the post already has.
        cover_image: [],
        cover_image_path: post.post_cover_image ?? '',
        meta_title: post.meta_title ?? '',
        meta_description: post.meta_description ?? '',
        meta_keywords: post.meta_keywords ?? '',
        category_uuid: post.category?.uuid ?? null,
        status: post.post_status,
        scheduled_at: toDateTimeLocal(post.scheduled_at),
        is_ai_generated: post.is_ai_generated,
        ai_provider: post.ai_provider,
        seo_score: post.seo_score,
        eeat_score: post.eeat_score,
        human_writing_index: post.human_writing_index,
        ai_detection_risk: post.ai_detection_risk,
        ai_scores: post.ai_scores,
    };
}

/**
 * Projects the form onto the exact body the endpoint accepts.
 *
 * Spelled out field by field rather than looped, because the return type is the
 * write payload: a missing or misnamed key is a compile error here instead of a
 * silently dropped column at runtime.
 */
export function toWritePayload(values: PostFormValues): PostWritePayload {
    return {
        title: values.title.trim(),
        content: values.content,
        excerpt: values.excerpt.trim() || null,
        cover_image: values.cover_image[0] ?? null,
        cover_image_path: values.cover_image_path.trim() || null,
        meta_title: values.meta_title.trim() || null,
        meta_description: values.meta_description.trim() || null,
        meta_keywords: values.meta_keywords.trim() || null,
        category_uuid: values.category_uuid || null,
        status: values.status,
        scheduled_at:
            values.status === 'scheduled'
                ? (values.scheduled_at ?? null)
                : null,
        is_ai_generated: values.is_ai_generated,
        ai_provider: values.ai_provider,
        seo_score: values.seo_score,
        eeat_score: values.eeat_score,
        human_writing_index: values.human_writing_index,
        ai_detection_risk: values.ai_detection_risk,
        ai_scores: values.ai_scores,
    };
}
