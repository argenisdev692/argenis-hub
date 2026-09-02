import { z } from 'zod';
import type { BlogCategory, BlogCategoryWritePayload } from '../types';

/**
 * The client-side mirror of `Modules\Blog\Application\DTOs\BlogCategoryData`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety.
 *
 * Two of the backend's rules are deliberately absent:
 *
 * - `Rule::unique('blog_categories', 'blog_category_name')` cannot be answered
 *   in the browser. A duplicate name comes back as a 422 and is projected onto
 *   the `name` field by `applyServerErrors`, so the operator still sees it
 *   under the input rather than in a toast.
 * - `dimensions:max_width=4096,max_height=4096` would mean decoding the file to
 *   read its intrinsic size before the form can be submitted. The size and mime
 *   checks below already reject the overwhelming majority of bad uploads, and
 *   the server rejects the rest.
 */

/** Matches the backend's `mimes:jpg,jpeg,png,webp`, by sniffed type. */
export const ACCEPTED_IMAGE_TYPES: readonly string[] = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

/** The backend's `max:2048` kilobytes, expressed in the unit the UI shows. */
export const MAX_IMAGE_MB = 2;

const MAX_IMAGE_BYTES = MAX_IMAGE_MB * 1024 * 1024;

export const blogCategoryFormSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, 'Name is required.')
        .max(255, 'Name must be 255 characters or fewer.'),
    description: z
        .string()
        .trim()
        .max(1000, 'Description must be 1000 characters or fewer.'),
    /**
     * `FileDropzone` models its value as `File[]` even in single-file mode, so
     * the schema validates a list of at most one rather than a bare `File`.
     * Empty is valid in both modes: on create the column is simply left null,
     * on edit the stored image is kept as-is.
     */
    image: z
        .array(z.instanceof(File))
        .max(1, 'Choose a single image.')
        .refine(
            (files) => files.every((file) => file.size <= MAX_IMAGE_BYTES),
            `The image must be ${MAX_IMAGE_MB} MB or smaller.`,
        )
        .refine(
            (files) =>
                files.every((file) => ACCEPTED_IMAGE_TYPES.includes(file.type)),
            'Use a JPEG, PNG or WebP image.',
        ),
});

export type BlogCategoryFormValues = z.infer<typeof blogCategoryFormSchema>;

export function emptyBlogCategoryFormValues(): BlogCategoryFormValues {
    return { name: '', description: '', image: [] };
}

/**
 * Seeds the edit form from a row.
 *
 * `image` always starts empty, never pre-filled from `image_url`: the field
 * means "replace the current image", and a `File` cannot be reconstructed from
 * a URL anyway. The dialog shows the stored image beside the dropzone instead,
 * so the operator can see what they are about to replace.
 */
export function toBlogCategoryFormValues(
    category: BlogCategory,
): BlogCategoryFormValues {
    return {
        name: category.blog_category_name ?? '',
        description: category.blog_category_description ?? '',
        image: [],
    };
}

/**
 * Projects the form onto the exact multipart body the endpoint accepts.
 *
 * Keys are omitted rather than sent empty, which is what decides two behaviours
 * on the server: an omitted `image` leaves `blog_category_image` untouched
 * (`UpdateBlogCategoryHandler` only replaces the object when a file arrives),
 * and an omitted `description` hydrates `BlogCategoryData::$description` as
 * `null` — so clearing the textarea genuinely clears the column instead of
 * storing an empty string.
 *
 * @param isUpdate Adds the `_method` spoof PHP needs to accept a multipart
 *   body on a route declared as `PUT`.
 */
export function toWritePayload(
    values: BlogCategoryFormValues,
    isUpdate: boolean,
): BlogCategoryWritePayload {
    const payload: BlogCategoryWritePayload = { name: values.name.trim() };

    const description = values.description.trim();

    if (description) {
        payload.description = description;
    }

    const [image] = values.image;

    if (image) {
        payload.image = image;
    }

    if (isUpdate) {
        payload._method = 'PUT';
    }

    return payload;
}
