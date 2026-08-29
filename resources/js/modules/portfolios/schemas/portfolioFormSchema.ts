import { z } from 'zod';
import type { Portfolio, PortfolioWritePayload } from '../types';

/**
 * The client-side mirror of `StorePortfolioRequest` / `UpdatePortfolioRequest`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety.
 *
 * ## Field-shape notes
 *
 * - `sort_order` is a string in the form: `TextField` always hands back a string
 *   (an `<input>` yields one), so the numeric conversion happens once, at the
 *   wire boundary, in `toWritePayload()`.
 * - `media` is `{ id, path }[]` rather than `string[]`: `SortableList` needs a
 *   stable key per row to animate reorders, and the backend never sees the id —
 *   `toWritePayload()` projects it back to the ordered `string[]` the endpoint
 *   wants, with display order = array index (see `SyncsPortfolioMedia`).
 */

const objectKey = z
    .string()
    .trim()
    .min(1, 'Path is required.')
    .max(500, 'Path must be 500 characters or fewer.');

export const portfolioFormSchema = z.object({
    title: z
        .string()
        .trim()
        .min(1, 'Title is required.')
        .max(255, 'Title must be 255 characters or fewer.'),
    client_name: z
        .string()
        .trim()
        .min(1, 'Client name is required.')
        .max(255, 'Client name must be 255 characters or fewer.'),
    project_type: z
        .string()
        .trim()
        .min(1, 'Project type is required.')
        .max(50, 'Project type must be 50 characters or fewer.'),
    tech_stack: z
        .array(
            z
                .string()
                .trim()
                .min(1, 'A tech entry cannot be empty.')
                .max(50, 'Each tech entry must be 50 characters or fewer.'),
        )
        .max(30, 'At most 30 tech entries.'),
    live_url: z.union([
        z.literal(''),
        z
            .string()
            .trim()
            .url('Enter a valid URL, including https://.')
            .max(500, 'URL must be 500 characters or fewer.'),
    ]),
    published_at: z.string().nullable(),
    is_public: z.boolean(),
    cover_path: z.union([
        z.literal(''),
        z.string().trim().max(500, 'Path must be 500 characters or fewer.'),
    ]),
    video_path: z.union([
        z.literal(''),
        z.string().trim().max(500, 'Path must be 500 characters or fewer.'),
    ]),
    description: z
        .string()
        .trim()
        .max(5000, 'Description must be 5000 characters or fewer.'),
    sort_order: z
        .string()
        .trim()
        .regex(/^\d+$/, 'Enter a whole number.')
        .refine((value) => Number(value) <= 2147483647, {
            message: 'Sort order is too large.',
        }),
    media: z
        .array(z.object({ id: z.string(), path: objectKey }))
        .max(50, 'At most 50 gallery items.'),
});

export type PortfolioFormValues = z.infer<typeof portfolioFormSchema>;

/** A blank local row for the gallery editor. */
export function newGalleryRow(path = ''): PortfolioFormValues['media'][number] {
    return { id: crypto.randomUUID(), path };
}

export function emptyPortfolioFormValues(): PortfolioFormValues {
    return {
        title: '',
        client_name: '',
        project_type: '',
        tech_stack: [],
        live_url: '',
        published_at: null,
        is_public: true,
        cover_path: '',
        video_path: '',
        description: '',
        sort_order: '0',
        media: [],
    };
}

export function toPortfolioFormValues(
    portfolio: Portfolio,
): PortfolioFormValues {
    return {
        title: portfolio.title,
        client_name: portfolio.client_name,
        project_type: portfolio.project_type,
        tech_stack: [...portfolio.tech_stack],
        live_url: portfolio.live_url ?? '',
        // `published_at` arrives as an ISO-8601 datetime; the form (and the
        // `date` rule behind it) only care about the calendar day.
        published_at: portfolio.published_at
            ? portfolio.published_at.slice(0, 10)
            : null,
        is_public: portfolio.is_public,
        cover_path: portfolio.cover_path ?? '',
        video_path: portfolio.video_path ?? '',
        description: portfolio.description ?? '',
        sort_order: String(portfolio.sort_order),
        media: portfolio.media_paths.map((path) => newGalleryRow(path)),
    };
}

/**
 * Projects the form onto the exact body the endpoint accepts.
 *
 * Spelled out field by field rather than looped, because the return type is the
 * generated write payload: a missing or misnamed key is a compile error here
 * instead of a silently dropped column at runtime.
 */
export function toWritePayload(
    values: PortfolioFormValues,
): PortfolioWritePayload {
    return {
        title: values.title.trim(),
        client_name: values.client_name.trim(),
        project_type: values.project_type.trim(),
        tech_stack: values.tech_stack
            .map((entry) => entry.trim())
            .filter(Boolean),
        live_url: values.live_url.trim() || null,
        published_at: values.published_at || null,
        is_public: values.is_public,
        cover_path: values.cover_path.trim() || null,
        video_path: values.video_path.trim() || null,
        description: values.description.trim() || null,
        sort_order: Number(values.sort_order),
        media: values.media.map((row) => row.path.trim()).filter(Boolean),
    };
}
