import { z } from 'zod';
import type { LogoVariant } from '../types';

/**
 * Mirrors `Modules\Company\Infrastructure\Http\Requests\UpdateCompanyLogosRequest`.
 *
 * All three marks are optional but at least one must be present, so the
 * operator can replace a single logo without re-uploading the other two.
 *
 * SVG is refused here for the same reason the backend refuses it: it is XML,
 * it can carry script, and it cannot be flattened by a raster re-encode, so
 * accepting it would mean serving attacker-controlled markup from the brand
 * asset origin. Keeping the client list identical to the server list means the
 * user finds out before the upload, not after.
 */

/** Matches the backend's `mimes:png,jpg,jpeg,webp`, by sniffed type. */
export const ACCEPTED_LOGO_TYPES: readonly string[] = [
    'image/png',
    'image/jpeg',
    'image/webp',
];

/** The backend's `max:4096` kilobytes, expressed in the unit the UI shows. */
export const MAX_LOGO_MB = 4;

const MAX_LOGO_BYTES = MAX_LOGO_MB * 1024 * 1024;

/**
 * `FileDropzone` models its value as `File[]` even in single-file mode, so the
 * schema validates a list of at most one rather than a bare `File`.
 */
const logoFile = z
    .array(z.instanceof(File))
    .max(1, 'Choose a single image for each mark.')
    .refine(
        (files) => files.every((file) => file.size <= MAX_LOGO_BYTES),
        `Each mark must be ${MAX_LOGO_MB} MB or smaller.`,
    )
    .refine(
        (files) =>
            files.every((file) => ACCEPTED_LOGO_TYPES.includes(file.type)),
        'Use a PNG, JPEG or WebP image.',
    );

export const companyLogosSchema = z
    .object({
        logo: logoFile,
        logo_white: logoFile,
        mark: logoFile,
    })
    .refine(
        (values) =>
            values.logo.length > 0 ||
            values.logo_white.length > 0 ||
            values.mark.length > 0,
        { message: 'Upload at least one brand mark.', path: ['logo'] },
    );

export type CompanyLogosFormValues = z.infer<typeof companyLogosSchema>;

export const EMPTY_LOGOS_FORM: CompanyLogosFormValues = {
    logo: [],
    logo_white: [],
    mark: [],
};

/**
 * Only the variants the operator actually chose reach the wire.
 *
 * Sending `logo_white: null` alongside a real `logo` would satisfy
 * `required_without_all` but hand the backend an empty file field to reason
 * about; omitting the key entirely is what the FormRequest expects.
 */
export function toLogosPayload(
    values: CompanyLogosFormValues,
): Record<string, File> {
    const variants: LogoVariant[] = ['logo', 'logo_white', 'mark'];
    const payload: Record<string, File> = {};

    for (const variant of variants) {
        const [file] = values[variant];

        if (file) {
            payload[variant] = file;
        }
    }

    return payload;
}
