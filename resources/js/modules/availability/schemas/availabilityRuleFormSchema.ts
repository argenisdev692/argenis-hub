import { z } from 'zod';
import type {
    AvailabilityRule,
    AvailabilityRuleWritePayload,
    DayOfWeek,
} from '../types';

/**
 * The client-side mirror of `Modules\Availability\Application\DTOs\AvailabilityRuleData`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety.
 *
 * One of the backend's rules is deliberately absent: the overlap check
 * (`noOverlapRule`) asks `AvailabilityRuleRepositoryPort::hasOverlappingAvailableSlot()`
 * a question only the database can answer. A conflicting slot comes back as a
 * 422 on `start_time` and is projected onto that field by `applyServerErrors`,
 * so the operator still reads it under the input rather than in a toast.
 */

/** `HH:MM`, 24-hour — the format `date_format:H:i` accepts. */
const TIME_PATTERN = /^([01]\d|2[0-3]):[0-5]\d$/;

const timeField = z
    .string()
    .trim()
    .regex(TIME_PATTERN, 'Use a 24-hour time, e.g. 09:00.');

export const availabilityRuleFormSchema = z
    .object({
        /**
         * Modelled as a number, not a string: `between:0,6` is an integer rule,
         * and `<Select>` values round-trip through the schema untouched, so
         * keeping the native type here avoids a cast at every read site.
         */
        day_of_week: z
            .number()
            .int()
            .min(0, 'Choose a weekday.')
            .max(6, 'Choose a weekday.'),
        start_time: timeField,
        end_time: timeField,
        is_available: z.boolean(),
    })
    /**
     * `after:start_time`, checked here as well so the operator does not have to
     * round-trip to learn that 17:00–09:00 is not a window. Attached to
     * `end_time` because that is the input they need to fix.
     */
    .refine((values) => values.end_time > values.start_time, {
        message: 'The end time must be later than the start time.',
        path: ['end_time'],
    });

export type AvailabilityRuleFormValues = z.infer<
    typeof availabilityRuleFormSchema
>;

/**
 * A fresh rule.
 *
 * Monday 09:00–17:00 rather than an empty form: the overwhelmingly common first
 * action is "add a normal working day", and a sensible default turns that into
 * one click instead of four fields.
 */
export function emptyAvailabilityRuleFormValues(): AvailabilityRuleFormValues {
    return {
        day_of_week: 1,
        start_time: '09:00',
        end_time: '17:00',
        is_available: true,
    };
}

/** Seeds the edit form from a row. */
export function toAvailabilityRuleFormValues(
    rule: AvailabilityRule,
): AvailabilityRuleFormValues {
    return {
        day_of_week: rule.day_of_week,
        start_time: rule.start_time,
        end_time: rule.end_time,
        is_available: rule.is_available,
    };
}

/**
 * Projects the form onto the exact body the endpoint accepts.
 *
 * `AvailabilityRuleData` carries `#[MapInputName(SnakeCaseMapper::class)]`, so
 * the wire names are the snake_case ones the form already uses — this is a
 * narrowing cast on `day_of_week` rather than a rename.
 */
export function toAvailabilityRuleWritePayload(
    values: AvailabilityRuleFormValues,
): AvailabilityRuleWritePayload {
    return {
        day_of_week: values.day_of_week as DayOfWeek,
        start_time: values.start_time,
        end_time: values.end_time,
        is_available: values.is_available,
    };
}
