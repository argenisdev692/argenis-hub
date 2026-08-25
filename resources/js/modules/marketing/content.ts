import {
    Banknote,
    Blocks,
    BotMessageSquare,
    CalendarDays,
    ChartLine,
    Handshake,
    ScanSearch,
    ShieldCheck,
    UserPlus,
    Workflow,
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
 */

export const NAV_LINKS: readonly MarketingLink[] = [
    { label: 'Platform', href: '#platform' },
    { label: 'Workflow', href: '#workflow' },
    { label: 'Results', href: '#results' },
] as const;

export const FEATURES: readonly MarketingFeature[] = [
    {
        id: 'ats',
        title: 'ATS matching that reads the CV, not the keywords',
        description:
            'Parse every applicant, score them against the live vacancy and surface the shortlist with the reasoning attached — so a hiring decision can be explained, not just made.',
        icon: ScanSearch,
        tone: 'cyan',
        span: 'wide',
        highlights: [
            'CV parsing and refinement',
            'Explainable match scores',
            'Shortlists that export clean',
        ],
    },
    {
        id: 'finance',
        title: 'Invoicing and cash flow',
        description:
            'Quotes, invoices and payment states in one ledger, with PDF exports that already carry your brand.',
        icon: Banknote,
        tone: 'gold',
        span: 'default',
    },
    {
        id: 'clients',
        title: 'Clients and pipeline',
        description:
            'Every contact, deal and touchpoint on one timeline. No tab-hopping to answer "where did we leave this?".',
        icon: Handshake,
        tone: 'purple',
        span: 'default',
    },
    {
        id: 'ai-content',
        title: 'AI content studio',
        description:
            'Draft posts, campaigns and outreach in your own voice, then schedule them without leaving the hub.',
        icon: BotMessageSquare,
        tone: 'magenta',
        span: 'default',
        highlights: ['Campaign and post drafting', 'Multi-channel scheduling'],
    },
    {
        id: 'scheduling',
        title: 'Meetings and availability',
        description:
            'Booking rules, exceptions and reminders that respect the calendar you already keep.',
        icon: CalendarDays,
        tone: 'indigo',
        span: 'default',
    },
    {
        id: 'analytics',
        title: 'Reporting worth opening',
        description:
            'Revenue, pipeline and hiring throughput in one view, exportable to Excel or PDF in a click.',
        icon: ChartLine,
        tone: 'cyan',
        span: 'default',
    },
] as const;

export const STEPS: readonly MarketingStep[] = [
    {
        id: 'connect',
        title: 'Bring your work in',
        description:
            'Import clients, vacancies and invoices, or start clean. Roles and permissions are set from day one.',
        icon: Blocks,
    },
    {
        id: 'automate',
        title: 'Let the routine run itself',
        description:
            'Matching, reminders, follow-ups and recurring invoices move without anyone chasing them.',
        icon: Workflow,
    },
    {
        id: 'grow',
        title: 'Decide on evidence',
        description:
            'Every action is logged and every number is traceable, so the report and the reality agree.',
        icon: UserPlus,
    },
] as const;

export const METRICS: readonly MarketingMetric[] = [
    {
        id: 'modules',
        value: '12+',
        label: 'Modules in one hub',
        caption: 'ATS, finance, content, scheduling and more',
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
