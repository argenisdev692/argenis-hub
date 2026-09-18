import { z } from 'zod';

/** `PasteJobTextData::rules()` → `min:50`, `max:100000`. The server stays authoritative. */
export const PASTE_MIN_LENGTH = 50;
export const PASTE_MAX_LENGTH = 100_000;

export const pasteJobTextSchema = z.object({
    text: z
        .string()
        .trim()
        .min(
            PASTE_MIN_LENGTH,
            `Paste at least ${PASTE_MIN_LENGTH} characters of the posting.`,
        )
        .max(
            PASTE_MAX_LENGTH,
            'That is longer than any real posting — trim it.',
        ),
});

export type PasteJobTextValues = z.infer<typeof pasteJobTextSchema>;

export function emptyPasteJobTextValues(): PasteJobTextValues {
    return { text: '' };
}
