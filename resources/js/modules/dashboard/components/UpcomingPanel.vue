<script setup lang="ts">
import { CalendarClock } from '@lucide/vue';
import EmptyState from '@/common/feedback/EmptyState.vue';
import { formatDateTime, formatRelativeTime } from '../helpers/format';
import type { UpcomingItem } from '../types';
import DashboardPanel from './DashboardPanel.vue';

const { items } = defineProps<{
    items: readonly UpcomingItem[];
}>();
</script>

<template>
    <DashboardPanel title="Coming up" description="Next few days">
        <ol v-if="items.length" class="space-y-3">
            <li
                v-for="item in items"
                :key="item.id"
                class="flex items-start gap-3 rounded-lg border border-glass-border px-3 py-3"
            >
                <CalendarClock
                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ item.title }}</p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ item.subject }}
                    </p>
                </div>

                <time
                    :datetime="item.startsAt"
                    :title="formatDateTime(item.startsAt)"
                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                >
                    {{ formatRelativeTime(item.startsAt) }}
                </time>
            </li>
        </ol>

        <EmptyState
            v-else
            :icon="CalendarClock"
            title="Nothing scheduled"
            description="Meetings, interviews and invoice due dates land here."
        />
    </DashboardPanel>
</template>
