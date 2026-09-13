import { z } from 'zod';
import { videoEditModePresentation } from '../helpers/videoEditPresentation';
import type { CreateVideoEditPayload } from '../types';

/**
 * The client-side mirror of `CreateVideoEditData::rules()` and
 * `config/video-edit.php`. The server re-validates every limit — and FFprobe
 * re-checks the real container after upload — so this buys fast feedback, not
 * safety (OWASP §8).
 */

export const MAX_SOURCES = 10;
export const MAX_SOURCE_MB = 2 * 1024;
export const MAX_SCRIPT_MB = 10;
export const MAX_MANUAL_RANGES = 500;
export const MAX_RANGE_NOTE_LENGTH = 120;
export const SILENCE_THRESHOLD = { min: 0.3, max: 10, default: 1 } as const;

/**
 * Extension → the MIME type the backend allow-lists. The declared type is
 * derived from the extension instead of `File.type`, which browsers leave
 * empty for `.mkv` and `.md` on several platforms.
 */
const VIDEO_MIME_BY_EXTENSION: Record<string, string> = {
    mp4: 'video/mp4',
    mov: 'video/quicktime',
    webm: 'video/webm',
    mkv: 'video/x-matroska',
};

const SCRIPT_MIME_BY_EXTENSION: Record<string, string> = {
    md: 'text/markdown',
    pdf: 'application/pdf',
};

export const ACCEPTED_VIDEO_TYPES: string[] = [
    ...Object.values(VIDEO_MIME_BY_EXTENSION),
    ...Object.keys(VIDEO_MIME_BY_EXTENSION).map((extension) => `.${extension}`),
];

export const ACCEPTED_SCRIPT_TYPES: string[] = [
    ...Object.values(SCRIPT_MIME_BY_EXTENSION),
    ...Object.keys(SCRIPT_MIME_BY_EXTENSION).map(
        (extension) => `.${extension}`,
    ),
];

function extensionOf(file: File): string {
    return file.name.split('.').pop()?.toLowerCase() ?? '';
}

const manualRangeSchema = z
    .object({
        start_seconds: z.number().min(0, 'Start cannot be negative.'),
        end_seconds: z.number().min(0, 'End cannot be negative.'),
        note: z
            .string()
            .max(
                MAX_RANGE_NOTE_LENGTH,
                `Notes are ${MAX_RANGE_NOTE_LENGTH} characters at most.`,
            ),
    })
    .refine((range) => range.end_seconds > range.start_seconds, {
        path: ['end_seconds'],
        message: 'Each range must end after it starts.',
    });

export type ManualRangeFormValue = z.infer<typeof manualRangeSchema>;

export const videoEditFormSchema = z
    .object({
        mode: z.enum(['merge', 'auto_edit', 'ai_edit']),
        sources: z
            .array(z.instanceof(File))
            .max(MAX_SOURCES, `Attach ${MAX_SOURCES} clips at most.`)
            .refine(
                (files) =>
                    files.every(
                        (file) => extensionOf(file) in VIDEO_MIME_BY_EXTENSION,
                    ),
                'Only MP4, MOV, WebM and MKV videos can be edited.',
            )
            .refine(
                (files) =>
                    files.every(
                        (file) => file.size <= MAX_SOURCE_MB * 1024 * 1024,
                    ),
                'Each clip must be 2 GB or smaller.',
            ),
        silence_enabled: z.boolean(),
        silence_threshold_seconds: z
            .number()
            .min(SILENCE_THRESHOLD.min, `At least ${SILENCE_THRESHOLD.min}s.`)
            .max(SILENCE_THRESHOLD.max, `At most ${SILENCE_THRESHOLD.max}s.`),
        speech_enabled: z.boolean(),
        speech_categories: z.array(
            z.enum([
                'filler',
                'filler_word',
                'stutter',
                'repetition',
                'vocal_sound',
            ]),
        ),
        manual_ranges: z
            .array(manualRangeSchema)
            .max(MAX_MANUAL_RANGES, `${MAX_MANUAL_RANGES} ranges at most.`),
        ai_consented: z.boolean(),
        ai_instructions: z.string().max(20000, 'Instructions are too long.'),
        ai_target_duration_minutes: z
            .number()
            .int('Use whole minutes.')
            .min(1, 'At least 1 minute.')
            .max(180, 'At most 180 minutes.')
            .nullable(),
        ai_script: z
            .array(z.instanceof(File))
            .max(1, 'Attach a single script.')
            .refine(
                (files) =>
                    files.every(
                        (file) => extensionOf(file) in SCRIPT_MIME_BY_EXTENSION,
                    ),
                'A script must be a .md or .pdf file.',
            )
            .refine(
                (files) =>
                    files.every(
                        (file) => file.size <= MAX_SCRIPT_MB * 1024 * 1024,
                    ),
                `The script must be ${MAX_SCRIPT_MB} MB or smaller.`,
            ),
    })
    .superRefine((values, context) => {
        const { minimumSources, label } = videoEditModePresentation(
            values.mode,
        );

        if (values.sources.length < minimumSources) {
            context.addIssue({
                code: 'custom',
                path: ['sources'],
                message: `${label} needs at least ${minimumSources} ${minimumSources === 1 ? 'clip' : 'clips'}.`,
            });
        }

        if (
            values.mode === 'auto_edit' &&
            !values.silence_enabled &&
            !values.speech_enabled &&
            values.manual_ranges.length === 0
        ) {
            context.addIssue({
                code: 'custom',
                path: ['silence_enabled'],
                message:
                    'Enable silence removal or speech cleanup, or add at least one range to cut.',
            });
        }

        if (values.mode === 'ai_edit' && !values.ai_consented) {
            context.addIssue({
                code: 'custom',
                path: ['ai_consented'],
                message:
                    'AI edit needs your consent to send the transcript and script to the AI provider.',
            });
        }
    });

export type VideoEditFormValues = z.infer<typeof videoEditFormSchema>;

export function emptyVideoEditFormValues(): VideoEditFormValues {
    return {
        mode: 'auto_edit',
        sources: [],
        silence_enabled: true,
        silence_threshold_seconds: SILENCE_THRESHOLD.default,
        speech_enabled: false,
        speech_categories: [],
        manual_ranges: [],
        ai_consented: false,
        ai_instructions: '',
        ai_target_duration_minutes: null,
        ai_script: [],
    };
}

/**
 * Projects the form onto the exact JSON body `POST /data/admin/video-edits`
 * accepts. Only metadata is sent — the files themselves go straight to storage
 * afterwards, through the presigned URLs the response carries.
 *
 * Blocks the chosen mode does not use are sent as `null` / `[]`, because the
 * backend *prohibits* them (e.g. `silence_removal` on a merge). AI edit reads
 * the speech-cleanup transcript, so that mode forces cleanup on.
 */
export function toCreateVideoEditPayload(
    values: VideoEditFormValues,
): CreateVideoEditPayload {
    const isMerge = values.mode === 'merge';
    const isAiEdit = values.mode === 'ai_edit';
    const [script] = values.ai_script;

    return {
        mode: values.mode,
        sources: values.sources.map((file, index) => ({
            position: index + 1,
            file_name: file.name,
            mime_type: VIDEO_MIME_BY_EXTENSION[extensionOf(file)] ?? file.type,
            size_bytes: file.size,
        })),
        silence_removal: isMerge
            ? null
            : {
                  enabled: values.silence_enabled,
                  threshold_seconds: values.silence_enabled
                      ? values.silence_threshold_seconds
                      : null,
              },
        speech_cleanup: isMerge
            ? null
            : {
                  enabled: isAiEdit || values.speech_enabled,
                  categories: values.speech_categories,
                  language: null,
              },
        ai_edit: isAiEdit
            ? {
                  enabled: true,
                  consented: values.ai_consented,
                  instructions: values.ai_instructions.trim() || null,
                  target_duration_minutes: values.ai_target_duration_minutes,
                  script: script
                      ? {
                            file_name: script.name,
                            mime_type:
                                SCRIPT_MIME_BY_EXTENSION[extensionOf(script)] ??
                                script.type,
                            size_bytes: script.size,
                        }
                      : null,
              }
            : null,
        manual_ranges: isMerge
            ? []
            : values.manual_ranges.map((range) => ({
                  start_ms: Math.round(range.start_seconds * 1000),
                  end_ms: Math.round(range.end_seconds * 1000),
                  note: range.note.trim() || null,
              })),
        previous_edit_uuid: null,
    };
}

/**
 * Laravel error keys (`silence_removal.threshold_seconds`, `sources.2.size_bytes`)
 * → the form field that renders them. Unknown keys fall back to `sources`, the
 * field every request has.
 */
const SERVER_FIELD_PREFIXES: [
    prefix: string,
    field: keyof VideoEditFormValues,
][] = [
    ['mode', 'mode'],
    ['sources', 'sources'],
    ['silence_removal.threshold_seconds', 'silence_threshold_seconds'],
    ['silence_removal', 'silence_enabled'],
    ['speech_cleanup', 'speech_enabled'],
    ['manual_ranges', 'manual_ranges'],
    ['ai_edit.consented', 'ai_consented'],
    ['ai_edit.instructions', 'ai_instructions'],
    ['ai_edit.target_duration_minutes', 'ai_target_duration_minutes'],
    ['ai_edit.script', 'ai_script'],
    ['ai_edit', 'ai_consented'],
];

export function toVideoEditFieldErrors(
    errors: Record<string, string[]>,
): Record<string, string> {
    const fieldErrors: Record<string, string> = {};

    for (const [key, messages] of Object.entries(errors)) {
        const message = messages[0];

        if (!message) {
            continue;
        }

        const field =
            SERVER_FIELD_PREFIXES.find(([prefix]) =>
                key.startsWith(prefix),
            )?.[1] ?? 'sources';

        fieldErrors[field] ??= message;
    }

    return fieldErrors;
}
