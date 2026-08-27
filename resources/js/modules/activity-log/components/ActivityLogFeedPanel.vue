<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ActivityIcon } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref } from 'vue';
import EmptyState from '@/common/feedback/EmptyState.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import DashboardPanel from '@/modules/dashboard/components/DashboardPanel.vue';
import { index, show } from '@/routes/activity-logs';
import { useActivityFeed } from '../composables/useActivityFeed';
import { activityLogEventPresentation } from '../helpers/activityLogEvent';
import {
    formatActivityAbsolute,
    formatActivityRelative,
} from '../helpers/formatActivityTimestamp';

/**
 * The dashboard's "Recent activity" panel, backed by the real audit trail.
 *
 * A vertical marquee: the list scrolls its own overflow upward on a slow loop
 * inside a fixed-height, clipped window (`marquee-vertical-track` +
 * `@keyframes marquee-vertical-scroll` in `app.css`). The rows are rendered
 * twice so a -50% translate loops seamlessly; hover or keyboard focus freezes
 * it via `data-paused`. Below the overflow threshold — or under
 * `prefers-reduced-motion` — it degrades to a plain (scrollable) list.
 *
 * `useActivityFeed` keeps the slice live on a visibility-gated poll.
 */

const { entries, isPending } = useActivityFeed();

const reduceMotion = useMediaQuery('(prefers-reduced-motion: reduce)');

const paused = ref(false);

const rows = computed(() =>
    entries.value.map((entry) => {
        const presentation = activityLogEventPresentation(entry.event);
        const subjectRef = entry.subject_type
            ? `${entry.subject_type}${entry.subject_id ? ` #${entry.subject_id}` : ''}`
            : null;

        return {
            id: entry.id,
            uuid: entry.uuid,
            icon: presentation.icon,
            chipClass: presentation.chipClass,
            title: entry.description,
            subject: [entry.causer_label ?? 'System', subjectRef]
                .filter(Boolean)
                .join(' · '),
            relative: formatActivityRelative(entry.created_at),
            absolute: formatActivityAbsolute(entry.created_at),
            datetime: entry.created_at ?? undefined,
        };
    }),
);

const showSkeleton = computed(() => isPending.value && rows.value.length === 0);

/** Only loop once there is more content than the window can hold. */
const animate = computed(() => !reduceMotion.value && rows.value.length > 4);

/** Longer list → longer loop, so the scroll speed stays roughly constant. */
const trackStyle = computed(() => ({
    '--marquee-vertical-duration': `${Math.max(18, rows.value.length * 3)}s`,
}));
</script>

<template>
    <DashboardPanel title="Recent activity" description="Across every module">
        <template #action>
            <Link
                :href="index()"
                class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
                View all
            </Link>
        </template>

        <ol v-if="showSkeleton" class="space-y-4">
            <li v-for="n in 5" :key="n" class="flex items-start gap-3">
                <Skeleton class="size-9 shrink-0 rounded-lg" />
                <div class="flex-1 space-y-2">
                    <Skeleton class="h-4 w-2/3" />
                    <Skeleton class="h-4 w-1/3" />
                </div>
            </li>
        </ol>

        <div
            v-else-if="rows.length"
            :class="
                cn(
                    'relative max-h-72',
                    animate
                        ? 'overflow-hidden [mask-image:linear-gradient(to_bottom,transparent,#000_12%,#000_88%,transparent)]'
                        : 'overflow-y-auto',
                )
            "
            @mouseenter="paused = true"
            @mouseleave="paused = false"
            @focusin="paused = true"
            @focusout="paused = false"
        >
            <ul
                :class="
                    cn('flex flex-col', animate && 'marquee-vertical-track')
                "
                :style="trackStyle"
                :data-paused="animate && paused ? 'true' : undefined"
            >
                <li v-for="row in rows" :key="row.uuid" class="shrink-0 py-0.5">
                    <Link
                        :href="show(row.id)"
                        class="-mx-2 flex items-start gap-3 rounded-lg px-2 py-1.5 transition-colors hover:bg-muted/60 focus-visible:bg-muted/60 focus-visible:outline-none"
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
                                class="size-4"
                                aria-hidden="true"
                            />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ row.title }}
                            </p>
                            <p class="truncate text-sm text-muted-foreground">
                                {{ row.subject }}
                            </p>
                        </div>

                        <time
                            :datetime="row.datetime"
                            :title="row.absolute"
                            class="shrink-0 text-xs text-muted-foreground tabular-nums"
                        >
                            {{ row.relative }}
                        </time>
                    </Link>
                </li>

                <!-- Second copy: the -50% loop lands it exactly where copy one
                     began. Hidden from AT and keyboard so nothing is announced
                     or focused twice. Only present while the loop runs. -->
                <li
                    v-for="row in animate ? rows : []"
                    :key="`echo-${row.uuid}`"
                    class="shrink-0 py-0.5"
                    aria-hidden="true"
                >
                    <span class="flex items-start gap-3 px-0 py-1.5">
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
                                class="size-4"
                                aria-hidden="true"
                            />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">
                                {{ row.title }}
                            </span>
                            <span
                                class="block truncate text-sm text-muted-foreground"
                            >
                                {{ row.subject }}
                            </span>
                        </span>

                        <span
                            class="shrink-0 text-xs text-muted-foreground tabular-nums"
                        >
                            {{ row.relative }}
                        </span>
                    </span>
                </li>
            </ul>
        </div>

        <EmptyState
            v-else
            :icon="ActivityIcon"
            title="Nothing has happened yet"
            description="Actions across every module will appear here as they land."
        />
    </DashboardPanel>
</template>
