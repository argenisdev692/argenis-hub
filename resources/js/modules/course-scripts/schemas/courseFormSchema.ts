import { z } from 'zod';
import type { CourseUploadLimits } from '../types';

/**
 * Client mirror of `StoreCourseRequest`. The server re-validates everything
 * (including real MIME sniffing); this schema only buys immediate feedback.
 *
 * Built from the `limits` page prop rather than hard-coded, so a change to
 * `config/course-scripts.php` can never drift from what the form enforces.
 */

/** Browsers report `.md` with an empty or `text/plain` type — list both. */
const MIME_TYPES = ['application/pdf', 'text/markdown', 'text/x-markdown'];

export function acceptedTypes(limits: CourseUploadLimits): string[] {
    return [
        ...MIME_TYPES,
        ...limits.allowed_extensions.map((extension) => `.${extension}`),
    ];
}

export function maxSizeMb(limits: CourseUploadLimits): number {
    return limits.max_kb / 1024;
}

function fileSchema(limits: CourseUploadLimits) {
    const extensions = limits.allowed_extensions.map((ext) => `.${ext}`);

    return z
        .instanceof(File)
        .refine(
            (file) => file.size <= limits.max_kb * 1024,
            `Each file must be ${maxSizeMb(limits)} MB or smaller.`,
        )
        .refine(
            (file) =>
                extensions.some((ext) => file.name.toLowerCase().endsWith(ext)),
            `Allowed formats: ${extensions.join(', ')}.`,
        );
}

export function courseFormSchema(limits: CourseUploadLimits) {
    return z.object({
        title: z
            .string()
            .trim()
            .max(255, 'Title must be 255 characters or fewer.'),
        index: z
            .array(fileSchema(limits))
            .length(1, 'Upload the course index (PDF or Markdown).'),
        contents: z
            .array(
                z.object({
                    file: fileSchema(limits),
                    video_number: z
                        .number()
                        .int('Use a whole number.')
                        .min(1, 'Video numbers start at 1.')
                        .nullable(),
                }),
            )
            .max(
                limits.max_content_files,
                `Attach at most ${limits.max_content_files} content files.`,
            ),
    });
}

export type CourseFormValues = z.infer<ReturnType<typeof courseFormSchema>>;

export type CourseContentEntry = CourseFormValues['contents'][number];

export function emptyCourseFormValues(): CourseFormValues {
    return { title: '', index: [], contents: [] };
}

/**
 * The multipart body `StoreCourseRequest` reads. `content_video_numbers` is
 * positional against `contents`; an empty string is how "not tied to a video"
 * survives `FormData` (the request maps `''` back to `null`).
 */
export function toCourseStorePayload(
    values: CourseFormValues,
): Record<string, unknown> {
    return {
        title: values.title.trim() || null,
        index: values.index[0],
        contents: values.contents.map((entry) => entry.file),
        content_video_numbers: values.contents.map(
            (entry) => entry.video_number ?? '',
        ),
    };
}

/**
 * Reconciles the dropzone's `File[]` with the entries already in the form,
 * matched by identity, so removing one file never shifts the video numbers
 * typed against the others.
 */
export function syncContentEntries(
    files: File[],
    current: CourseContentEntry[],
): CourseContentEntry[] {
    return files.map(
        (file) =>
            current.find((entry) => entry.file === file) ?? {
                file,
                video_number: null,
            },
    );
}
