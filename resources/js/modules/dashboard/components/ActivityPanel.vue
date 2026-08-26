<script setup lang="ts">
import { Activity } from '@lucide/vue';
import { m } from 'motion-v';
import { computed } from 'vue';
import EmptyState from '@/common/feedback/EmptyState.vue';
import {
    MOTION_STAGGER_TIGHT,
    REVEAL_ITEM,
    staggerContainer,
} from '@/lib/motion';
import { cn } from '@/lib/utils';
import { toneClasses } from '@/modules/marketing/helpers/toneClasses';
import { activityPresentation } from '../helpers/activityIcon';
import { formatDateTime, formatRelativeTime } from '../helpers/format';
import type { ActivityEntry } from '../types';
import DashboardPanel from './DashboardPanel.vue';

const { entries } = defineProps<{
    entries: readonly ActivityEntry[];
}>();

/**
 * Resolve the glyph and tone once per entry instead of calling the lookups
 * from three places in the template.
 */
const rows = computed(() =>
    entries.map((entry) => {
        const { icon, tone } = activityPresentation(entry.kind);
        const classes = toneClasses(tone);

        return {
            entry,
            icon,
            chipClass: classes.chip,
            iconClass: classes.icon,
        };
    }),
);

/**
 * `delayChildren` holds the rows until the panel around them has arrived.
 * Without it the list is sliding upward inside a surface that is itself still
 * sliding upward, which reads as two things moving rather than one.
 *
 * The list drives itself (`initial` / `animate`) rather than inheriting from
 * the panel, so it behaves the same wherever the panel is placed.
 */
const rowCascade = staggerContainer(
    MOTION_STAGGER_TIGHT,
    MOTION_STAGGER_TIGHT * 3,
);
</script>

<template>
    <DashboardPanel title="Recent activity" description="Across every module">
        <m.ol
            v-if="rows.length"
            class="space-y-4"
            :variants="rowCascade"
            initial="hidden"
            animate="visible"
        >
            <m.li
                v-for="row in rows"
                :key="row.entry.id"
                class="flex items-start gap-3"
                :variants="REVEAL_ITEM"
            >
                <span
                    :class="
                        cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                            row.chipClass,
                        )
                    "
                >
                    <component
                        :is="row.icon"
                        :class="cn('size-4', row.iconClass)"
                        aria-hidden="true"
                    />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">
                        {{ row.entry.title }}
                    </p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ row.entry.subject }}
                    </p>
                </div>

                <!-- Relative for scanning, absolute in `datetime` and `title`
                     so the exact moment is never lost. -->
                <time
                    :datetime="row.entry.occurredAt"
                    :title="formatDateTime(row.entry.occurredAt)"
                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                >
                    {{ formatRelativeTime(row.entry.occurredAt) }}
                </time>
            </m.li>
        </m.ol>

        <EmptyState
            v-else
            :icon="Activity"
            title="Nothing has happened yet"
            description="Invoices, matches and bookings will appear here as they land."
        />
    </DashboardPanel>
</template>
