import type { LucideIcon } from '@lucide/vue';
import type { BrandTone } from '@/modules/marketing/types';

/** Direction of a period-over-period change. `flat` covers "no movement". */
export type TrendDirection = 'up' | 'down' | 'flat';

/**
 * Whether an upward move is good news.
 *
 * Revenue up is good; overdue invoices up is not. Colour is chosen from this,
 * never from the direction alone — and it is always paired with an arrow and a
 * signed number, so the meaning survives a colour-vision deficiency
 * (WCAG 1.4.1).
 */
export type TrendPolarity = 'positive-up' | 'positive-down';

export type KpiMetric = {
    id: string;
    label: string;
    /** Pre-formatted for display — formatting is a server/helper concern. */
    value: string;
    /** Signed percentage change over the comparison window, e.g. `12.4`. */
    changePercent: number;
    /** What the change is measured against, e.g. "vs last month". */
    comparison: string;
    polarity: TrendPolarity;
    icon: LucideIcon;
    tone: BrandTone;
    /** Trend series for the tile's sparkline. Oldest value first. */
    series: readonly number[];
};

export type PipelineStage = {
    id: string;
    label: string;
    /** Deal count in this stage. */
    count: number;
    /** Total value, pre-formatted. */
    value: string;
    /** Share of the pipeline, 0–100. */
    share: number;
};

export type ActivityKind =
    | 'invoice_paid'
    | 'candidate_matched'
    | 'client_added'
    | 'meeting_booked'
    | 'campaign_sent';

export type ActivityEntry = {
    id: string;
    kind: ActivityKind;
    title: string;
    subject: string;
    /** ISO-8601 timestamp. Rendered relative, with the absolute value in
     *  `<time datetime>` so it stays machine-readable. */
    occurredAt: string;
};

export type UpcomingItem = {
    id: string;
    title: string;
    subject: string;
    /** ISO-8601 timestamp. */
    startsAt: string;
};

export type RevenuePoint = {
    /** Short month label, e.g. "Mar". */
    label: string;
    /** Invoiced amount for the period, in the account's base currency. */
    amount: number;
};

/**
 * Everything the dashboard renders.
 *
 * Declared as one prop object so the page can move from fixture to server
 * props by changing where the object comes from, not how it is consumed.
 */
export type DashboardOverview = {
    kpis: readonly KpiMetric[];
    revenue: readonly RevenuePoint[];
    pipeline: readonly PipelineStage[];
    activity: readonly ActivityEntry[];
    upcoming: readonly UpcomingItem[];
};
