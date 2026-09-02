import { z } from 'zod';
import type { StandardSchema } from '@/common/form';
import type { Cv, CvWritePayload } from '../types';

/**
 * The client-side mirror of `Modules\Cvs\Application\DTOs\UploadCvData`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety. `FileDropzone`'s
 * own checks are a third, even weaker, copy for the same reason.
 */

/** The backend's `max:5120` kilobytes, expressed in the unit the UI shows. */
export const MAX_CV_MB = 5;

const MAX_CV_BYTES = MAX_CV_MB * 1024 * 1024;

/**
 * What the file picker offers and the schema re-checks.
 *
 * Extensions are listed alongside the mime types on purpose. `mimetypes:` on
 * the server sniffs the real content, but in the browser a `.md` file very
 * often arrives with an empty `File.type` — no OS mapping for it — so a
 * mime-only check would reject exactly the format this module was built to
 * accept. Both lists mirror `UploadCvData::rules()`: `extensions:pdf,md,markdown`
 * and `mimetypes:text/markdown,text/plain,text/x-markdown,application/pdf`.
 */
export const ACCEPTED_CV_TYPES: readonly string[] = [
    'application/pdf',
    'text/markdown',
    'text/x-markdown',
    'text/plain',
    '.pdf',
    '.md',
    '.markdown',
];

const ACCEPTED_CV_EXTENSIONS = ['.pdf', '.md', '.markdown'];

function hasAcceptedExtension(file: File): boolean {
    const name = file.name.toLowerCase();

    return ACCEPTED_CV_EXTENSIONS.some((extension) => name.endsWith(extension));
}

/**
 * The shape shared by both modes.
 *
 * `file` models its value as `File[]` even though at most one is ever sent:
 * that is what `FileDropzone` binds, so the schema validates a list of at most
 * one rather than a bare `File`.
 */
export const cvFormSchema = z.object({
    title: z
        .string()
        .trim()
        .min(1, 'Title is required.')
        .max(255, 'Title must be 255 characters or fewer.'),
    niche: z.enum(['fullstack', 'other']),
    is_primary: z.boolean(),
    file: z
        .array(z.instanceof(File))
        .max(1, 'Choose a single file.')
        .refine(
            (files) => files.every((file) => file.size <= MAX_CV_BYTES),
            `The file must be ${MAX_CV_MB} MB or smaller.`,
        )
        .refine(
            (files) => files.every(hasAcceptedExtension),
            'Upload a PDF or a Markdown (.md) file.',
        ),
});

export type CvFormValues = z.infer<typeof cvFormSchema>;

/**
 * Create mode additionally requires the file.
 *
 * `CreateCvHandler` throws `ValidationException::withMessages(['file' => …])`
 * when it is missing, so leaving this to the server would work — it would just
 * cost a full round trip to say something the browser already knows. Update
 * mode keeps it optional: an omitted file is how `UpdateCvHandler` is told to
 * keep the stored R2 object and its extracted text.
 */
export const cvCreateFormSchema = cvFormSchema.refine(
    (values) => values.file.length === 1,
    { path: ['file'], message: 'A CV file (PDF or Markdown) is required.' },
);

/**
 * One validator for a dialog that serves both modes.
 *
 * `useAppForm` takes a single schema at construction time, but the dialog
 * instance is reused for create and edit, and only `isUpdate()` — read fresh on
 * every keystroke — knows which set of rules applies. Delegating through the
 * Standard Schema interface both schemas already implement keeps that decision
 * in one place instead of building two form instances.
 */
export function cvModeAwareSchema(isUpdate: () => boolean): StandardSchema {
    return {
        '~standard': {
            version: 1,
            vendor: 'cvs',
            validate: (value: unknown) =>
                (isUpdate() ? cvFormSchema : cvCreateFormSchema)[
                    '~standard'
                ].validate(value),
        },
    };
}

export function emptyCvFormValues(): CvFormValues {
    return { title: '', niche: 'fullstack', is_primary: false, file: [] };
}

/**
 * Seeds the edit form from a row.
 *
 * `file` always starts empty, never pre-filled from the stored upload: the
 * field means "replace the current file", and a `File` cannot be reconstructed
 * from an R2 key anyway. The dialog names the stored filename beside the
 * dropzone instead, so the operator can see what they are about to replace.
 */
export function toCvFormValues(cv: Cv): CvFormValues {
    return {
        title: cv.title,
        niche: cv.niche,
        is_primary: cv.is_primary,
        file: [],
    };
}

/**
 * Projects the form onto the exact multipart body the endpoint accepts.
 *
 * `file` is omitted rather than sent empty, which is what decides the behaviour
 * on the server: `UpdateCvHandler` only uploads a new object, re-extracts
 * `raw_text` and deletes the previous R2 key when a file actually arrives.
 */
export function toCvWritePayload(values: CvFormValues): CvWritePayload {
    const payload: CvWritePayload = {
        title: values.title.trim(),
        niche: values.niche,
        is_primary: values.is_primary,
    };

    const [file] = values.file;

    if (file) {
        payload.file = file;
    }

    return payload;
}
