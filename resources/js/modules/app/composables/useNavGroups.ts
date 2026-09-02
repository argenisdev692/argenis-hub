import {
    Banknote,
    BotMessageSquare,
    Briefcase,
    CalendarDays,
    ChartLine,
    DatabaseBackup,
    FileText,
    GalleryVerticalEnd,
    Handshake,
    KeyRound,
    Layers,
    LayoutGrid,
    LifeBuoy,
    Megaphone,
    ScrollText,
    Share2,
    ShieldCheck,
    Tags,
    Users,
    UsersRound,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { index as activityLogIndex } from '@/routes/activity-logs';
import { index as backupsIndex } from '@/routes/backups';
import { index as blogCategoriesIndex } from '@/routes/blog-categories';
import { index as campaignsIndex } from '@/routes/campaigns';
import { index as clientsIndex } from '@/routes/clients';
import { index as contactSupportsIndex } from '@/routes/contact-supports';
import { index as permissionsIndex } from '@/routes/permissions';
import { index as portfoliosIndex } from '@/routes/portfolios';
import { index as postsIndex } from '@/routes/posts';
import { edit as editProfile } from '@/routes/profile';
import { index as rolesIndex } from '@/routes/roles';
import { edit as editSecurity } from '@/routes/security';
import { index as servicesIndex } from '@/routes/services';
import { index as socialMediaIndex } from '@/routes/social-media';
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
 * Items carrying a `permission` are dropped for users who lack it (and a group
 * left empty drops with them); the route behind each one is independently
 * guarded by `permission:*` middleware.
 */
export function useNavGroups(): readonly NavGroup[] {
    const { can } = usePermissions();

    const groups: NavGroup[] = [
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
                {
                    title: 'Activity log',
                    href: activityLogIndex(),
                    icon: ScrollText,
                    permission: 'VIEW_ANY_ACTIVITY_LOGS',
                },
                {
                    title: 'Backups',
                    href: backupsIndex(),
                    icon: DatabaseBackup,
                    permission: 'VIEW_ANY_BACKUPS',
                },
                {
                    title: 'Support inbox',
                    href: contactSupportsIndex(),
                    icon: LifeBuoy,
                    permission: 'VIEW_ANY_CONTACT_SUPPORTS',
                },
            ],
        },
        {
            id: 'revenue',
            label: 'Revenue',
            items: [
                {
                    title: 'Clients',
                    href: clientsIndex(),
                    icon: Handshake,
                    permission: 'VIEW_ANY_CLIENTS',
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
                    title: 'Portfolio',
                    href: portfoliosIndex(),
                    icon: GalleryVerticalEnd,
                    permission: 'VIEW_ANY_PORTFOLIOS',
                },
                {
                    title: 'Blog categories',
                    href: blogCategoriesIndex(),
                    icon: Tags,
                    permission: 'VIEW_ANY_BLOG_CATEGORIES',
                },
                {
                    title: 'Posts',
                    href: postsIndex(),
                    icon: BotMessageSquare,
                    permission: 'VIEW_ANY_POSTS',
                },
                {
                    title: 'Social media',
                    href: socialMediaIndex(),
                    icon: Share2,
                    permission: 'VIEW_ANY_SOCIAL_MEDIA',
                },
                {
                    title: 'Campaigns',
                    href: campaignsIndex(),
                    icon: Megaphone,
                    permission: 'VIEW_ANY_CAMPAIGNS',
                },
            ],
        },
        {
            id: 'access-control',
            label: 'Access control',
            items: [
                {
                    title: 'Roles',
                    href: rolesIndex(),
                    icon: UsersRound,
                    permission: 'VIEW_ANY_ROLES',
                },
                {
                    title: 'Permissions',
                    href: permissionsIndex(),
                    icon: KeyRound,
                    permission: 'VIEW_ANY_PERMISSIONS',
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
    ];

    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.permission || can(item.permission),
            ),
        }))
        .filter((group) => group.items.length > 0);
}
