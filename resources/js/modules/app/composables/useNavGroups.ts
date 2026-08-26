import {
    Banknote,
    BotMessageSquare,
    Briefcase,
    CalendarDays,
    ChartLine,
    FileText,
    Handshake,
    Layers,
    LayoutGrid,
    Megaphone,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as servicesIndex } from '@/routes/services';
import type { NavItem } from '@/types';

export type NavGroup = {
    id: string;
    label: string;
    items: readonly NavGroupItem[];
};

export type NavGroupItem = NavItem & {
    icon: LucideIcon;
    /** Set while the destination has no route yet — renders disabled, with a
     *  "Soon" marker, instead of linking somewhere that 404s. */
    comingSoon?: boolean;
};

/**
 * The sidebar's information architecture.
 *
 * Grouped rather than flat because Miller's 7±2 applies to navigation too: a
 * single list of a dozen destinations is a list nobody reads. Four labelled
 * groups of three or four are scannable.
 *
 * Items whose modules have not shipped are marked `comingSoon` and rendered
 * disabled — the shape of the product stays visible without any dead links.
 */
export function useNavGroups(): readonly NavGroup[] {
    return [
        {
            id: 'workspace',
            label: 'Workspace',
            items: [
                { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
                {
                    title: 'Calendar',
                    href: dashboard(),
                    icon: CalendarDays,
                    comingSoon: true,
                },
            ],
        },
        {
            id: 'revenue',
            label: 'Revenue',
            items: [
                {
                    title: 'Clients',
                    href: dashboard(),
                    icon: Handshake,
                    comingSoon: true,
                },
                {
                    title: 'Invoices',
                    href: dashboard(),
                    icon: Banknote,
                    comingSoon: true,
                },
                {
                    title: 'Reports',
                    href: dashboard(),
                    icon: ChartLine,
                    comingSoon: true,
                },
            ],
        },
        {
            id: 'talent',
            label: 'Talent',
            items: [
                {
                    title: 'Vacancies',
                    href: dashboard(),
                    icon: Briefcase,
                    comingSoon: true,
                },
                {
                    title: 'Candidates',
                    href: dashboard(),
                    icon: Users,
                    comingSoon: true,
                },
                {
                    title: 'CVs',
                    href: dashboard(),
                    icon: FileText,
                    comingSoon: true,
                },
            ],
        },
        {
            id: 'content',
            label: 'Content',
            items: [
                {
                    title: 'Services',
                    href: servicesIndex(),
                    icon: Layers,
                },
                {
                    title: 'Posts',
                    href: dashboard(),
                    icon: BotMessageSquare,
                    comingSoon: true,
                },
                {
                    title: 'Campaigns',
                    href: dashboard(),
                    icon: Megaphone,
                    comingSoon: true,
                },
            ],
        },
        {
            id: 'account',
            label: 'Account',
            items: [
                { title: 'Profile', href: editProfile(), icon: Users },
                { title: 'Security', href: editSecurity(), icon: ShieldCheck },
            ],
        },
    ] as const;
}
