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
            value: formatCurrency(5),
            changePercent: 0,
            comparison: 'vs last month',
            polarity: 'positive-up',
            icon: Banknote,
            tone: 'gold',
            series: [0, 0, 0, 0, 0, 0, 2, 3, 4, 5],
        },
        {
            id: 'pipeline',
            label: 'Open pipeline',
            value: formatCurrency(150),
            changePercent: 0,
            comparison: 'vs last month',
            polarity: 'positive-up',
            icon: Handshake,
            tone: 'purple',
            series: [0, 0, 20, 40, 60, 90, 110, 130, 140, 150],
        },
        {
            id: 'vacancies',
            label: 'Active vacancies',
            value: '3',
            changePercent: 0,
            comparison: 'vs last month',
            polarity: 'positive-up',
            icon: Briefcase,
            tone: 'cyan',
            series: [0, 0, 1, 1, 2, 2, 3, 3, 3, 3],
        },
        {
            id: 'overdue',
            label: 'Overdue invoices',
            value: formatCurrency(0),
            changePercent: 0,
            comparison: 'vs last month',
            // More overdue is worse, so an upward move must read as red.
            polarity: 'positive-down',
            icon: ReceiptText,
            tone: 'magenta',
            series: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        },
    ],

    revenue: [
        { label: 'Nov', amount: 0 },
        { label: 'Dec', amount: 0 },
        { label: 'Jan', amount: 0 },
        { label: 'Feb', amount: 0 },
        { label: 'Mar', amount: 0 },
        { label: 'Apr', amount: 0 },
        { label: 'May', amount: 0 },
        { label: 'Jun', amount: 0 },
        { label: 'Jul', amount: 0 },
        { label: 'Aug', amount: 5 },
    ],

    pipeline: [
        {
            id: 'qualified',
            label: 'Qualified',
            count: 1,
            value: formatCurrency(50),
            share: 33,
        },
        {
            id: 'proposal',
            label: 'Proposal sent',
            count: 1,
            value: formatCurrency(60),
            share: 40,
        },
        {
            id: 'negotiation',
            label: 'Negotiation',
            count: 1,
            value: formatCurrency(40),
            share: 27,
        },
    ],

    activity: [
        {
            id: 'a1',
            kind: 'invoice_paid',
            title: 'Expense of $5 recorded',
            subject: 'Early setup costs',
            occurredAt: hoursAgo(2),
        },
        {
            id: 'a2',
            kind: 'candidate_matched',
            title: '3 applications submitted',
            subject: 'First vacancies',
            occurredAt: hoursAgo(5),
        },
        {
            id: 'a3',
            kind: 'client_added',
            title: 'First lead added',
            subject: 'Personal network',
            occurredAt: hoursAgo(9),
        },
        {
            id: 'a4',
            kind: 'campaign_sent',
            title: 'Profile set up',
            subject: 'Getting started',
            occurredAt: hoursAgo(26),
        },
        {
            id: 'a5',
            kind: 'meeting_booked',
            title: 'Discovery call booked',
            subject: 'First prospect',
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
