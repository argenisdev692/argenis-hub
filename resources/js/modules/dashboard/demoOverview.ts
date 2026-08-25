import { Banknote, Briefcase, Handshake, ReceiptText } from '@lucide/vue';
import { formatCurrency } from './helpers/format';
import type { DashboardOverview } from './types';

/**
 * ⚠️ PLACEHOLDER DATA — NOT REAL FIGURES.
 *
 * The dashboard route is currently `Route::inertia('dashboard', 'Dashboard')`
 * with no controller behind it, and none of the CRM modules (clients,
 * invoices, vacancies) have shipped yet. This fixture exists so the layout,
 * the empty/loading states and the contrast maths can be built and reviewed
 * against realistic shapes.
 *
 * To go live: build a `DashboardController` that returns a `DashboardData`
 * Spatie Data object matching `./types`, hand it to `Inertia::render` as the
 * `overview` prop, and delete this file. `pages/Dashboard.vue` already prefers
 * the prop when it is present, so nothing else has to change.
 */

/** Anchored to "now" so the relative timestamps stay sensible on any day. */
const hoursAgo = (hours: number): string =>
    new Date(Date.now() - hours * 60 * 60 * 1000).toISOString();

const hoursAhead = (hours: number): string =>
    new Date(Date.now() + hours * 60 * 60 * 1000).toISOString();

export const DEMO_OVERVIEW: DashboardOverview = {
    kpis: [
        {
            id: 'revenue',
            label: 'Revenue this month',
            value: formatCurrency(48250),
            changePercent: 12.4,
            comparison: 'vs last month',
            polarity: 'positive-up',
            icon: Banknote,
            tone: 'gold',
            series: [28, 31, 29, 36, 34, 42, 39, 45, 43, 48],
        },
        {
            id: 'pipeline',
            label: 'Open pipeline',
            value: formatCurrency(126400),
            changePercent: 5.1,
            comparison: 'vs last month',
            polarity: 'positive-up',
            icon: Handshake,
            tone: 'purple',
            series: [96, 102, 99, 108, 112, 110, 118, 121, 124, 126],
        },
        {
            id: 'vacancies',
            label: 'Active vacancies',
            value: '18',
            changePercent: 0,
            comparison: 'vs last month',
            polarity: 'positive-up',
            icon: Briefcase,
            tone: 'cyan',
            series: [14, 15, 17, 16, 18, 18, 17, 19, 18, 18],
        },
        {
            id: 'overdue',
            label: 'Overdue invoices',
            value: formatCurrency(7900),
            changePercent: 8.2,
            comparison: 'vs last month',
            // More overdue is worse, so an upward move must read as red.
            polarity: 'positive-down',
            icon: ReceiptText,
            tone: 'magenta',
            series: [4.2, 5.1, 4.8, 6.0, 5.6, 6.4, 6.9, 7.1, 7.4, 7.9],
        },
    ],

    revenue: [
        { label: 'Nov', amount: 31200 },
        { label: 'Dec', amount: 28400 },
        { label: 'Jan', amount: 34900 },
        { label: 'Feb', amount: 33100 },
        { label: 'Mar', amount: 39600 },
        { label: 'Apr', amount: 37800 },
        { label: 'May', amount: 42300 },
        { label: 'Jun', amount: 44100 },
        { label: 'Jul', amount: 41700 },
        { label: 'Aug', amount: 48250 },
    ],

    pipeline: [
        {
            id: 'qualified',
            label: 'Qualified',
            count: 14,
            value: formatCurrency(52800),
            share: 42,
        },
        {
            id: 'proposal',
            label: 'Proposal sent',
            count: 9,
            value: formatCurrency(38600),
            share: 31,
        },
        {
            id: 'negotiation',
            label: 'Negotiation',
            count: 5,
            value: formatCurrency(24500),
            share: 19,
        },
        {
            id: 'closing',
            label: 'Closing',
            count: 2,
            value: formatCurrency(10500),
            share: 8,
        },
    ],

    activity: [
        {
            id: 'a1',
            kind: 'invoice_paid',
            title: 'Invoice INV-2041 paid',
            subject: 'Northwind Studio',
            occurredAt: hoursAgo(2),
        },
        {
            id: 'a2',
            kind: 'candidate_matched',
            title: '6 candidates matched',
            subject: 'Senior Laravel Engineer',
            occurredAt: hoursAgo(5),
        },
        {
            id: 'a3',
            kind: 'client_added',
            title: 'New client added',
            subject: 'Meridian Labs',
            occurredAt: hoursAgo(9),
        },
        {
            id: 'a4',
            kind: 'campaign_sent',
            title: 'Campaign sent to 340 contacts',
            subject: 'Q3 re-engagement',
            occurredAt: hoursAgo(26),
        },
        {
            id: 'a5',
            kind: 'meeting_booked',
            title: 'Discovery call booked',
            subject: 'Atlas Freight',
            occurredAt: hoursAgo(31),
        },
    ],

    upcoming: [
        {
            id: 'u1',
            title: 'Contract review',
            subject: 'Northwind Studio',
            startsAt: hoursAhead(3),
        },
        {
            id: 'u2',
            title: 'Candidate interview',
            subject: 'Senior Laravel Engineer',
            startsAt: hoursAhead(22),
        },
        {
            id: 'u3',
            title: 'Invoice INV-2048 due',
            subject: 'Atlas Freight',
            startsAt: hoursAhead(49),
        },
    ],
};
