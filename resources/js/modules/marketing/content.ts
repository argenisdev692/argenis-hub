import {
    BotMessageSquare,
    CalendarCheck,
    Megaphone,
    Receipt,
    ScanSearch,
    ShieldCheck,
    Sparkles,
    Workflow,
    Wallet,
} from '@lucide/vue';
import type {
    MarketingFeature,
    MarketingLink,
    MarketingMetric,
    MarketingStep,
} from './types';

/**
 * Landing copy and structure, kept out of the templates.
 *
 * Sections render whatever is in these arrays, so adding a capability is a
 * one-object edit rather than a markup change — and the shape is type-checked
 * against `./types`.
 *
 * Scope discipline: this page describes the five modules the hub actually
 * ships — AI content, lead campaigns, ATS, appointments and invoicing. A
 * capability that does not exist in the product does not get a card here.
 */

export const NAV_LINKS: readonly MarketingLink[] = [
    { label: 'Modules', href: '#platform' },
    { label: 'Workflow', href: '#workflow' },
    { label: 'Results', href: '#results' },
] as const;

export const FEATURES: readonly MarketingFeature[] = [
    {
        id: 'ai-content',
        title: 'AI content studio for posts and social media',
        description:
            'Brief it once with your company profile and voice, then generate post copy, hooks and variants per channel — drafted, reviewed and scheduled without leaving the hub.',
        icon: BotMessageSquare,
        tone: 'magenta',
        span: 'wide',
        highlights: [
            'Post and caption generation in your own voice',
            'One idea, reformatted per social channel',
            'Draft → review → schedule in one place',
        ],
    },
    {
        id: 'campaigns',
        title: 'Lead campaigns',
        description:
            'Plan a campaign, let AI write the sequence, and watch the leads it brings in land on a pipeline you can actually work.',
        icon: Megaphone,
        tone: 'purple',
        span: 'default',
    },
    {
        id: 'ats',
        title: 'ATS: CV optimisation and job matching',
        description:
            'Rewrite a CV against a real posting, score the match and surface the jobs worth applying to — with the reasoning attached.',
        icon: ScanSearch,
        tone: 'cyan',
        span: 'default',
        highlights: ['CV parsing and rewrite', 'Explainable match scores'],
    },
    {
        id: 'appointments',
        title: 'Appointments',
        description:
            'Availability rules, bookings, exceptions and reminders that respect the calendar you already keep.',
        icon: CalendarCheck,
        tone: 'indigo',
        span: 'default',
    },
    {
        id: 'invoices',
        title: 'Invoicing',
        description:
            'Issue invoices, track what is paid and export a branded PDF that already carries your fiscal and bank details.',
        icon: Receipt,
        tone: 'gold',
        span: 'default',
    },
] as const;

export const STEPS: readonly MarketingStep[] = [
    {
        id: 'create',
        title: 'Create',
        description:
            'AI drafts the posts, the campaign sequence and the CV rewrite from your own profile — you edit, you approve.',
        icon: Sparkles,
    },
    {
        id: 'convert',
        title: 'Convert',
        description:
            'Campaigns bring in leads, leads book appointments, and the booking lands on the calendar with the reminder already set.',
        icon: Workflow,
    },
    {
        id: 'collect',
        title: 'Collect',
        description:
            'The work done becomes an invoice and a branded PDF, with every step logged so the report and the reality agree.',
        icon: Wallet,
    },
] as const;

export const METRICS: readonly MarketingMetric[] = [
    {
        id: 'modules',
        value: '5',
        label: 'Modules in one hub',
        caption: 'Content, campaigns, ATS, appointments, invoicing',
    },
    {
        id: 'audit',
        value: '100%',
        label: 'Actions audited',
        caption: 'Every change carries who, what and when',
    },
    {
        id: 'exports',
        value: '2',
        label: 'Export formats everywhere',
        caption: 'Excel and branded PDF on every list',
    },
    {
        id: 'a11y',
        value: 'AA',
        label: 'WCAG 2.2 baseline',
        caption: 'Contrast, focus and keyboard paths verified',
    },
] as const;

export const TRUST_POINTS: readonly { id: string; label: string }[] = [
    { id: 'mfa', label: 'Two-factor and passkeys' },
    { id: 'rbac', label: 'Role-based permissions' },
    { id: 'audit', label: 'Full activity trail' },
] as const;

/** Icon for the security strip under the hero. Single source, single import. */
export const TRUST_ICON = ShieldCheck;

/**
 * Display order and labels for the footer's social row.
 *
 * Re-exported from `common/brand` rather than declared here: the company
 * settings screen edits the same six channels, and two lists meant the footer
 * could label a channel "X" while the form still called it "Twitter". The URLs
 * come from `company_data`, so this only decides order and wording — a channel
 * with no URL in the database is simply skipped.
 */
export { SOCIAL_LABELS, SOCIAL_ORDER } from '@/common/brand/socialChannels';
