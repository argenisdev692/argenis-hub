import type { CalendarDate } from '@internationalized/date';
import {
    endOfMonth,
    getLocalTimeZone,
    startOfMonth,
    startOfYear,
    today,
} from '@internationalized/date';
import type { DateRange } from './types';

/**
 * One-click `created_at` windows for `DataTableDateRangeFilter`.
 *
 * Pure and clock-injectable (`now`) so a preset resolves the same way in a
 * test as on screen. Every bound is an ISO `YYYY-MM-DD` string — the wire shape
 * of `DateRange` — never a JS `Date`.
 */

export type DateRangePreset = {
    label: string;
    resolve: (now: CalendarDate) => DateRange;
};

function span(from: CalendarDate, to: CalendarDate): DateRange {
    return { from: from.toString(), to: to.toString() };
}

export const DATE_RANGE_PRESETS: readonly DateRangePreset[] = [
    { label: 'Today', resolve: (now) => span(now, now) },
    {
        label: 'Yesterday',
        resolve: (now) => {
            const yesterday = now.subtract({ days: 1 });

            return span(yesterday, yesterday);
        },
    },
    {
        label: 'Last 7 days',
        resolve: (now) => span(now.subtract({ days: 6 }), now),
    },
    {
        label: 'Last 30 days',
        resolve: (now) => span(now.subtract({ days: 29 }), now),
    },
    { label: 'This month', resolve: (now) => span(startOfMonth(now), now) },
    {
        label: 'Last month',
        resolve: (now) => {
            const lastMonth = now.subtract({ months: 1 });

            return span(startOfMonth(lastMonth), endOfMonth(lastMonth));
        },
    },
    { label: 'This year', resolve: (now) => span(startOfYear(now), now) },
];

export function todayInLocalZone(): CalendarDate {
    return today(getLocalTimeZone());
}

/** The preset whose window equals `range`, so the trigger can name it. */
export function matchingPreset(
    range: DateRange,
    now: CalendarDate = todayInLocalZone(),
): DateRangePreset | undefined {
    return DATE_RANGE_PRESETS.find((preset) => {
        const candidate = preset.resolve(now);

        return candidate.from === range.from && candidate.to === range.to;
    });
}
