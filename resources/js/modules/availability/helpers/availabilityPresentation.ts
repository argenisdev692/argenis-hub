import {
    CalendarOffIcon,
    CircleSlashIcon,
    ClockIcon,
    DoorOpenIcon,
    FlagIcon,
    UserPenIcon,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { BadgeVariants } from '@/components/ui/badge';
import type {
    AvailabilityException,
    AvailabilityExceptionDetail,
    AvailabilityRule,
    AvailabilityRuleDetail,
    DayOfWeek,
    ExceptionSource,
} from '../types';

/**
 * Row-derived display values shared by the tables, the dialogs and the
 * confirmation modals, so "how a rule or an exception reads in the UI" is
 * decided once for both entities.
 */

export type StatusPresentation = {
    label: string;
    variant: NonNullable<BadgeVariants['variant']>;
    icon: LucideIcon;
};

// ---- weekdays ---------------------------------------------------------------

/**
 * Index = `day_of_week`, so `0` is Sunday — the value `between:0,6` validates
 * and the one `Carbon::dayOfWeek` produces. Starting the week on Sunday here is
 * not a style choice; shifting it would silently mislabel every row.
 */
const DAY_NAMES = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
] as const;

const DAY_ABBREVIATIONS = [
    'Sun',
    'Mon',
    'Tue',
    'Wed',
    'Thu',
    'Fri',
    'Sat',
] as const;

/** Every weekday, in the order the select and the table read them. */
export const DAY_OF_WEEK_VALUES: readonly DayOfWeek[] = [
    0, 1, 2, 3, 4, 5, 6,
] as const;

export function dayOfWeekLabel(day: DayOfWeek): string {
    return DAY_NAMES[day];
}

export function dayOfWeekAbbreviation(day: DayOfWeek): string {
    return DAY_ABBREVIATIONS[day];
}

/** Narrowing guard for values arriving from a `<Select>`, which yields unknown. */
export function isDayOfWeek(value: unknown): value is DayOfWeek {
    return (
        typeof value === 'number' &&
        Number.isInteger(value) &&
        value >= 0 &&
        value <= 6
    );
}

// ---- times and dates --------------------------------------------------------

/**
 * `HH:MM` → `9:00 AM`.
 *
 * Parsed by hand rather than through `Date`: the value is a wall-clock time with
 * no date and no zone, and routing it through `new Date('09:00')` would invent
 * both — which is how a 9 AM slot renders as 8 AM for half the year.
 */
export function formatTime(time: string | null): string | null {
    if (!time) {
        return null;
    }

    const [rawHours, rawMinutes] = time.split(':');
    const hours = Number(rawHours);
    const minutes = Number(rawMinutes);

    if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
        return time;
    }

    const period = hours < 12 ? 'AM' : 'PM';
    const displayHours = hours % 12 === 0 ? 12 : hours % 12;

    return `${displayHours}:${String(minutes).padStart(2, '0')} ${period}`;
}

/** The window a rule or a forced-open exception covers, as one readable span. */
export function formatTimeRange(
    start: string | null,
    end: string | null,
): string | null {
    const from = formatTime(start);
    const to = formatTime(end);

    if (!from || !to) {
        return null;
    }

    return `${from} – ${to}`;
}

/**
 * `YYYY-MM-DD` → "3 Jun 2026".
 *
 * Split and rebuilt through `Date.UTC` rather than handed to `new Date(iso)`:
 * a bare date string is parsed as UTC midnight and then formatted in the
 * viewer's zone, which renders the 3rd as the 2nd anywhere west of Greenwich.
 */
export function formatDateOnly(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    const [year, month, day] = iso.slice(0, 10).split('-').map(Number);

    if (!year || !month || !day) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month - 1, day)));
}

/**
 * ISO8601 timestamp → "3 Jun 2026", or null when there is none.
 *
 * Distinct from `formatDateOnly`: this one takes a full timestamp (`created_at`,
 * `deleted_at`) and is correct to localise, because the instant it names really
 * did happen at a point in time. A bare `YYYY-MM-DD` has no instant to localise
 * and goes through `formatDateOnly` instead.
 */
export function formatDate(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(iso));
}

/** ISO8601 timestamp → "3 Jun 2026, 14:05", or null when there is none. */
export function formatDateTime(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

/** Today as `YYYY-MM-DD` in the viewer's own zone — the create form's floor. */
export function todayIso(): string {
    const now = new Date();

    return [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
    ].join('-');
}

// ---- status -----------------------------------------------------------------

const ACTIVE: StatusPresentation = {
    label: 'Active',
    variant: 'default',
    icon: ClockIcon,
};

/**
 * "Suspended", not "Deleted", because that is the word the backend uses
 * everywhere the operator can see it: the filter value is `status=suspended`,
 * and `destroy()` flashes "Availability rule suspended.".
 */
const SUSPENDED: StatusPresentation = {
    label: 'Suspended',
    variant: 'destructive',
    icon: CircleSlashIcon,
};

/**
 * Neither entity has a lifecycle column — soft deletion is the whole of its
 * status, so `deleted_at` is the only input.
 */
export function availabilityStatusPresentation(
    deletedAt: string | null,
): StatusPresentation {
    return deletedAt === null ? ACTIVE : SUSPENDED;
}

// ---- availability -----------------------------------------------------------

const OPEN: StatusPresentation = {
    label: 'Open',
    variant: 'secondary',
    icon: DoorOpenIcon,
};

const CLOSED: StatusPresentation = {
    label: 'Closed',
    variant: 'outline',
    icon: CalendarOffIcon,
};

/**
 * The `is_available` flag, as a badge.
 *
 * Deliberately NOT `destructive` for a closure: a closed day is a normal,
 * intended state of the calendar, and painting every weekend red would drown
 * out the one colour that means "this row is suspended".
 */
export function availabilityPresentation(
    isAvailable: boolean,
): StatusPresentation {
    return isAvailable ? OPEN : CLOSED;
}

/** The wording a rule uses — a weekly template is "available", not "open". */
export function ruleAvailabilityLabel(isAvailable: boolean): string {
    return isAvailable ? 'Available' : 'Unavailable';
}

// ---- exception source -------------------------------------------------------

const MANUAL: StatusPresentation = {
    label: 'Manual',
    variant: 'outline',
    icon: UserPenIcon,
};

/**
 * A holiday row is system-materialised and is purged and rebuilt by the yearly
 * sync (and by a country change), so an edit to one does not survive. The badge
 * exists to warn the operator before they spend the effort.
 */
const HOLIDAY: StatusPresentation = {
    label: 'Holiday',
    variant: 'secondary',
    icon: FlagIcon,
};

export function exceptionSourcePresentation(
    source: ExceptionSource,
): StatusPresentation {
    return source === 'holiday' ? HOLIDAY : MANUAL;
}

/** True for rows the holiday sync owns — see `HolidayMaterializer`. */
export function isSystemManaged(exception: AvailabilityException): boolean {
    return exception.source === 'holiday';
}

// ---- labels -----------------------------------------------------------------

/**
 * The label every confirmation modal, toast and `aria-label` uses for one rule.
 * Falls back to the weekday alone rather than an empty string, so a row is
 * always identifiable in a "suspend this?" prompt.
 */
export function availabilityRuleLabel(
    rule: AvailabilityRule | AvailabilityRuleDetail,
): string {
    const range = formatTimeRange(rule.start_time, rule.end_time);

    return range
        ? `${dayOfWeekLabel(rule.day_of_week)}, ${range}`
        : dayOfWeekLabel(rule.day_of_week);
}

/** The same, for one exception: the date, plus the reason when there is one. */
export function availabilityExceptionLabel(
    exception: AvailabilityException | AvailabilityExceptionDetail,
): string {
    const date = formatDateOnly(exception.date) ?? exception.date;
    const reason = exception.reason?.trim();

    return reason ? `${date} — ${reason}` : date;
}
