import { z } from 'zod';
import { todayIso } from '../helpers/availabilityPresentation';
import type {
    AvailabilityException,
    AvailabilityExceptionWritePayload,
} from '../types';

/**
 * The client-side mirror of
 * `Modules\Availability\Application\DTOs\AvailabilityExceptionData`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety.
 *
 * One of the backend's rules is deliberately absent:
 * `Rule::unique('availability_exceptions', 'date')` cannot be answered in the
 * browser. A duplicate date comes back as a 422 and is projected onto the `date`
 * field by `applyServerErrors`, so the operator reads it under the input rather
 * than in a toast.
 */

/** `HH:MM`, 24-hour — the format `date_format:H:i` accepts. */
const TIME_PATTERN = /^([01]\d|2[0-3]):[0-5]\d$/;

/** `YYYY-MM-DD` — the format `date_format:Y-m-d` accepts. */
const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

const optionalTimeField = z
    .string()
    .trim()
    .refine(
        (value) => value === '' || TIME_PATTERN.test(value),
        'Use a 24-hour time, e.g. 09:00.',
    );

const baseShape = z.object({
    date: z.string().trim().regex(DATE_PATTERN, 'Choose a date.'),
    /**
     * Defaults to a closure. A date exception is overwhelmingly "we are shut
     * that day"; a forced-open day is the rarer, more deliberate case, and it is
     * the one that then demands hours.
     */
    is_available: z.boolean(),
    /** Empty string, not null: both time inputs are `<input type="time">`. */
    start_time: optionalTimeField,
    end_time: optionalTimeField,
    reason: z
        .string()
        .trim()
        .max(255, 'The reason must be 255 characters or fewer.'),
});

export type AvailabilityExceptionFormValues = z.infer<typeof baseShape>;

/**
 * The form schema, built around a live `isUpdate` getter.
 *
 * A getter rather than a boolean because one dialog instance serves both modes:
 * the operator opens it to create, closes it, then opens it again on a row to
 * edit, without the component ever unmounting. A schema that captured the mode
 * at setup would still be enforcing the create rules on that second open.
 *
 * What actually differs between the modes is one rule.
 * `AvailabilityExceptionData::rules()` applies `after_or_equal:today` on create
 * only, so an already-past exception stays correctable. Mirrored here: a
 * permissive-in-both-modes schema would push a past date to a 422 the operator
 * could have been spared, and a strict-in-both-modes one would make an existing
 * row uneditable.
 *
 * The conditional hours are the other half: a forced-open day
 * (`is_available = true`) MUST carry both times, a closure needs neither (and
 * the handler clears whatever is sent). Expressed as a `superRefine` rather than
 * a discriminated union because the operator toggles `is_available` inside a
 * live form — a union would swap the whole schema underneath a half-filled field
 * and lose what they had typed.
 */
export function availabilityExceptionFormSchema(isUpdate: () => boolean) {
    return baseShape.superRefine((values, ctx) => {
        if (!isUpdate() && values.date && values.date < todayIso()) {
            ctx.addIssue({
                code: 'custom',
                path: ['date'],
                message: 'A new exception must be dated today or later.',
            });
        }

        if (!values.is_available) {
            return;
        }

        if (!values.start_time) {
            ctx.addIssue({
                code: 'custom',
                path: ['start_time'],
                message: 'A forced-open day needs a start time.',
            });
        }

        if (!values.end_time) {
            ctx.addIssue({
                code: 'custom',
                path: ['end_time'],
                message: 'A forced-open day needs an end time.',
            });

            return;
        }

        // `after:start_time`, mirrored so 17:00–09:00 is caught before submit.
        if (values.start_time && values.end_time <= values.start_time) {
            ctx.addIssue({
                code: 'custom',
                path: ['end_time'],
                message: 'The end time must be later than the start time.',
            });
        }
    });
}

export function emptyAvailabilityExceptionFormValues(): AvailabilityExceptionFormValues {
    return {
        date: todayIso(),
        is_available: false,
        start_time: '',
        end_time: '',
        reason: '',
    };
}

/** Seeds the edit form from a row. */
export function toAvailabilityExceptionFormValues(
    exception: AvailabilityException,
): AvailabilityExceptionFormValues {
    return {
        date: exception.date.slice(0, 10),
        is_available: exception.is_available,
        start_time: exception.start_time ?? '',
        end_time: exception.end_time ?? '',
        reason: exception.reason ?? '',
    };
}

/**
 * Projects the form onto the exact body the endpoint accepts.
 *
 * Keys are omitted rather than sent empty, which is what decides two behaviours
 * on the server: omitted hours hydrate `AvailabilityExceptionData::$startTime`
 * and `$endTime` as null (which the handler stores, clearing a day that used to
 * be forced-open), and an omitted `reason` clears the column instead of storing
 * an empty string. On a closure the hours are dropped unconditionally, so
 * leftovers from a toggled-back form never reach the wire.
 */
export function toAvailabilityExceptionWritePayload(
    values: AvailabilityExceptionFormValues,
): AvailabilityExceptionWritePayload {
    const payload: AvailabilityExceptionWritePayload = {
        date: values.date,
        is_available: values.is_available,
    };

    if (values.is_available) {
        if (values.start_time) {
            payload.start_time = values.start_time;
        }

        if (values.end_time) {
            payload.end_time = values.end_time;
        }
    }

    const reason = values.reason.trim();

    if (reason) {
        payload.reason = reason;
    }

    return payload;
}
