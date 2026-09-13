import {
    CircleCheckIcon,
    CircleDashedIcon,
    CircleSlashIcon,
    FileTextIcon,
    LoaderCircleIcon,
    PauseCircleIcon,
    SparklesIcon,
    TriangleAlertIcon,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { BadgeVariants } from '@/components/ui/badge';
import type {
    CourseStatus,
    SourceDocumentKind,
    VideoScriptStatus,
} from '../types';

/**
 * Display values shared by the table, the detail page and the modals, so how a
 * course reads in the UI is decided once.
 */

export type Presentation = {
    label: string;
    variant: NonNullable<BadgeVariants['variant']>;
    icon: LucideIcon;
};

const COURSE_STATUSES: Record<CourseStatus, Presentation> = {
    draft: { label: 'Draft', variant: 'outline', icon: CircleDashedIcon },
    ready: { label: 'Ready', variant: 'secondary', icon: PauseCircleIcon },
    generating: {
        label: 'Generating',
        variant: 'secondary',
        icon: LoaderCircleIcon,
    },
    partially_generated: {
        label: 'Partially generated',
        variant: 'secondary',
        icon: SparklesIcon,
    },
    completed: {
        label: 'Completed',
        variant: 'default',
        icon: CircleCheckIcon,
    },
};

/** In declaration order of the PHP enum — also the filter's option order. */
export const COURSE_STATUSES_ORDERED = Object.keys(
    COURSE_STATUSES,
) as CourseStatus[];

const DELETED: Presentation = {
    label: 'Deleted',
    variant: 'destructive',
    icon: CircleSlashIcon,
};

/** A soft-deleted course reads as deleted whatever its generation status was. */
export function courseStatusPresentation(
    status: CourseStatus,
    deletedAt: string | null = null,
): Presentation {
    return deletedAt === null ? COURSE_STATUSES[status] : DELETED;
}

const SCRIPT_STATUSES: Record<VideoScriptStatus, Presentation> = {
    not_started: {
        label: 'Not started',
        variant: 'outline',
        icon: CircleDashedIcon,
    },
    generating: {
        label: 'Generating',
        variant: 'secondary',
        icon: LoaderCircleIcon,
    },
    generated: {
        label: 'Generated',
        variant: 'default',
        icon: CircleCheckIcon,
    },
    failed: {
        label: 'Failed',
        variant: 'destructive',
        icon: TriangleAlertIcon,
    },
};

export function scriptStatusPresentation(
    status: VideoScriptStatus,
): Presentation {
    return SCRIPT_STATUSES[status];
}

const DOCUMENT_KINDS: Record<SourceDocumentKind, string> = {
    index: 'Index',
    content: 'Content',
    style_reference: 'Style reference',
};

export function documentKindLabel(kind: SourceDocumentKind): string {
    return DOCUMENT_KINDS[kind];
}

export const DOCUMENT_ICON: LucideIcon = FileTextIcon;

/** "3 of 12" — the generation progress a row shows. */
export function generatedProgress(generated: number, total: number): string {
    return `${generated} of ${total}`;
}

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

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function pluralize(
    count: number,
    singular: string,
    plural: string,
): string {
    return `${count} ${count === 1 ? singular : plural}`;
}
