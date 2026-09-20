import { z } from 'zod';
import type {
    BibleOrganisation,
    CourseBible,
    CourseDetail,
    CourseNotesPayload,
    CourseUploadLimits,
    CourseVideo,
    VideoBriefPayload,
} from '../types';
import { fileSchema } from './courseFormSchema';

/**
 * Client mirrors of the course page's edit endpoints — `UpdateCourseNotesData`,
 * `UpdateVideoBriefData`, `CourseBibleData` and `AttachSourceDocumentRequest`.
 * The server re-validates everything; these buy immediate feedback only, so
 * each limit below is the backend's, never a stricter invention.
 */

const optionalText = (max: number) =>
    z.string().trim().max(max, `Keep this under ${max} characters.`);

/** A string list edited through `TagsInput` (Enter per entry). */
const textList = (maxItems: number, maxLength: number) =>
    z
        .array(
            z
                .string()
                .trim()
                .min(1)
                .max(
                    maxLength,
                    `Each entry must be ${maxLength} characters or fewer.`,
                ),
        )
        .max(maxItems, `Add at most ${maxItems} entries.`);

/** Empty input means "not set" on every nullable text column. */
function nullIfBlank(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

// ---- course notes -----------------------------------------------------------

export const courseNotesSchema = z.object({
    course_notes: optionalText(60000),
});

export type CourseNotesFormValues = z.infer<typeof courseNotesSchema>;

export function toCourseNotesFormValues(
    course: Pick<CourseDetail, 'course_notes'>,
): CourseNotesFormValues {
    return { course_notes: course.course_notes ?? '' };
}

export function toCourseNotesPayload(
    values: CourseNotesFormValues,
): CourseNotesPayload {
    return { course_notes: nullIfBlank(values.course_notes) };
}

// ---- video brief --------------------------------------------------------------

export const videoBriefSchema = z.object({
    title: z.string().trim().min(1, 'Give the video a title.').max(255),
    topic: optionalText(255),
    declared_duration_minutes: z
        .number()
        .int('Use whole minutes.')
        .min(1, 'At least 1 minute.')
        .max(180, 'At most 180 minutes.')
        .nullable(),
    objective: optionalText(4000),
    learning_areas: textList(30, 500),
    audience_objectives: textList(30, 500),
    mandatory_content: textList(30, 500),
    errors_to_avoid: textList(30, 500),
    expected_result: optionalText(4000),
    notes: optionalText(60000),
});

export type VideoBriefFormValues = z.infer<typeof videoBriefSchema>;

/** The seed while no video is picked — the sheet only opens once one is. */
export function emptyVideoBriefFormValues(): VideoBriefFormValues {
    return {
        title: '',
        topic: '',
        declared_duration_minutes: null,
        objective: '',
        learning_areas: [],
        audience_objectives: [],
        mandatory_content: [],
        errors_to_avoid: [],
        expected_result: '',
        notes: '',
    };
}

export function toVideoBriefFormValues(
    video: CourseVideo,
): VideoBriefFormValues {
    return {
        title: video.title,
        topic: video.topic ?? '',
        declared_duration_minutes: video.declared_duration_minutes,
        objective: video.objective ?? '',
        learning_areas: [...video.learning_areas],
        audience_objectives: [...video.audience_objectives],
        mandatory_content: [...video.mandatory_content],
        errors_to_avoid: [...video.errors_to_avoid],
        expected_result: video.expected_result ?? '',
        notes: video.notes ?? '',
    };
}

export function toVideoBriefPayload(
    values: VideoBriefFormValues,
): VideoBriefPayload {
    return {
        title: values.title.trim(),
        topic: nullIfBlank(values.topic),
        declared_duration_minutes: values.declared_duration_minutes,
        objective: nullIfBlank(values.objective),
        learning_areas: values.learning_areas,
        audience_objectives: values.audience_objectives,
        mandatory_content: values.mandatory_content,
        errors_to_avoid: values.errors_to_avoid,
        expected_result: nullIfBlank(values.expected_result),
        notes: nullIfBlank(values.notes),
    };
}

// ---- course bible -------------------------------------------------------------

const organisationSchema = z.object({
    key: z.string().min(1).max(60),
    name: z.string().trim().min(1, 'Name the organisation.').max(160),
    role: optionalText(200),
    sector: optionalText(120),
    is_primary: z.boolean(),
});

const characterSchema = z.object({
    name: z.string().trim().min(1, 'Name the character.').max(120),
    role: optionalText(200),
    organisation_key: z.string().max(60).nullable(),
});

export const courseBibleSchema = z
    .object({
        organisations: z
            .array(organisationSchema)
            .max(30, 'At most 30 organisations.'),
        characters: z.array(characterSchema).max(40, 'At most 40 characters.'),
        audience: optionalText(1000),
        tone: optionalText(500),
        taught_tool: optionalText(160),
        forbidden_phrasings: textList(50, 200),
    })
    // `CourseBibleData::withValidator` — the rule the server enforces on save.
    .refine(
        (bible) =>
            bible.organisations.length === 0 ||
            bible.organisations.filter(
                (organisation) => organisation.is_primary,
            ).length === 1,
        {
            error: 'Mark exactly one organisation as primary.',
            path: ['organisations'],
        },
    )
    .refine(
        (bible) =>
            bible.characters.every(
                (character) =>
                    character.organisation_key === null ||
                    bible.organisations.some(
                        (organisation) =>
                            organisation.key === character.organisation_key,
                    ),
            ),
        {
            error: 'A character points at a removed organisation.',
            path: ['characters'],
        },
    );

export type CourseBibleFormValues = z.infer<typeof courseBibleSchema>;

export type OrganisationRow = CourseBibleFormValues['organisations'][number];

export type CharacterRow = CourseBibleFormValues['characters'][number];

export function toCourseBibleFormValues(
    bible: CourseBible | null,
): CourseBibleFormValues {
    return {
        organisations: (bible?.organisations ?? []).map(
            (organisation: BibleOrganisation) => ({ ...organisation }),
        ),
        characters: (bible?.characters ?? []).map((character) => ({
            name: character.name,
            role: character.role,
            organisation_key: character.organisation_key,
        })),
        audience: bible?.audience ?? '',
        tone: bible?.tone ?? '',
        taught_tool: bible?.taught_tool ?? '',
        forbidden_phrasings: [...(bible?.forbidden_phrasings ?? [])],
    };
}

export function toCourseBiblePayload(
    values: CourseBibleFormValues,
): CourseBible {
    return {
        organisations: values.organisations,
        characters: values.characters,
        audience: values.audience.trim(),
        tone: values.tone.trim(),
        taught_tool: nullIfBlank(values.taught_tool),
        forbidden_phrasings: values.forbidden_phrasings,
    };
}

/**
 * A new organisation row. Its `key` is what characters reference, so it is
 * minted once here and never derived from the (editable) name. The first
 * organisation starts as the primary one — the rule needs exactly one.
 */
export function newOrganisationRow(
    existing: readonly OrganisationRow[],
): OrganisationRow {
    return {
        key: `org-${crypto.randomUUID().slice(0, 8)}`,
        name: '',
        role: '',
        sector: '',
        is_primary: existing.length === 0,
    };
}

export function newCharacterRow(): CharacterRow {
    return { name: '', role: '', organisation_key: null };
}

// ---- attach source document ------------------------------------------------------

export const ATTACHABLE_KINDS = ['content', 'style_reference'] as const;

export type AttachableKind = (typeof ATTACHABLE_KINDS)[number];

export function attachDocumentSchema(limits: CourseUploadLimits) {
    return z.object({
        kind: z.enum(ATTACHABLE_KINDS),
        files: z
            .array(fileSchema(limits))
            .length(1, 'Choose one file to attach.'),
        video_number: z
            .number()
            .int('Use a whole number.')
            .min(1, 'Video numbers start at 1.')
            .nullable(),
    });
}

export type AttachDocumentFormValues = z.infer<
    ReturnType<typeof attachDocumentSchema>
>;

export function emptyAttachDocumentValues(): AttachDocumentFormValues {
    return { kind: 'content', files: [], video_number: null };
}

/**
 * The multipart body `AttachSourceDocumentRequest` reads. A style reference
 * is course-wide, so any video number typed before switching kind is dropped.
 */
export function toAttachDocumentFormData(
    values: AttachDocumentFormValues,
): FormData {
    const body = new FormData();
    const file = values.files[0];

    if (file) {
        body.append('file', file);
    }

    if (values.kind === 'content' && values.video_number !== null) {
        body.append('video_number', String(values.video_number));
    }

    return body;
}
