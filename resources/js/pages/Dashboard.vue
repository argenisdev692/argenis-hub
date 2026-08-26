<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Download, Plus } from '@lucide/vue';
import { m } from 'motion-v';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    MOTION_STAGGER_TIGHT,
    REVEAL_FADE,
    staggerContainer,
} from '@/lib/motion';
import ActivityPanel from '@/modules/dashboard/components/ActivityPanel.vue';
import KpiCard from '@/modules/dashboard/components/KpiCard.vue';
import PipelinePanel from '@/modules/dashboard/components/PipelinePanel.vue';
import RevenuePanel from '@/modules/dashboard/components/RevenuePanel.vue';
import UpcomingPanel from '@/modules/dashboard/components/UpcomingPanel.vue';
import { DEMO_OVERVIEW } from '@/modules/dashboard/demoOverview';
import type { DashboardOverview } from '@/modules/dashboard/types';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

/**
 * `overview` is optional while the CRM modules are still being built: no
 * controller supplies it yet, so the page falls back to the placeholder
 * fixture. Once a `DashboardController` sends the prop it takes over with no
 * further change here — see `modules/dashboard/demoOverview.ts`.
 */
const { overview } = defineProps<{
    overview?: DashboardOverview;
}>();

const data = computed<DashboardOverview>(() => overview ?? DEMO_OVERVIEW);

const firstName = computed(() => usePage().props.auth.user?.first_name ?? '');

const today = computed(() =>
    new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(new Date()),
);

/**
 * Three short cascades that all start on mount, rather than one cascade down
 * the page.
 *
 * Two deliberate departures from the landing page. Nothing here waits for
 * scroll: `while-in-view` would mean a panel already on screen animates on a
 * revisit only if the viewport happens to trip the observer, and a returning
 * user should never be watching content arrive that was in front of them a
 * second ago. And the groups run in parallel instead of in sequence, so the
 * last panel is settled in roughly a fifth of a second no matter how many
 * tiles the row holds.
 */
const groupCascade = staggerContainer(MOTION_STAGGER_TIGHT);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <m.header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            :variants="REVEAL_FADE"
            initial="hidden"
            animate="visible"
        >
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ firstName ? `Welcome back, ${firstName}` : 'Overview' }}
                </h1>
                <p class="text-sm text-muted-foreground">{{ today }}</p>
            </div>

            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm">
                    <Download />
                    Export
                </Button>
                <Button size="sm">
                    <Plus />
                    New invoice
                </Button>
            </div>
        </m.header>

        <!-- Four tiles, deliberately: the metrics people check daily stay one
             glance away instead of competing with a wall of widgets. -->
        <m.section
            aria-label="Key metrics"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            :variants="groupCascade"
            initial="hidden"
            animate="visible"
        >
            <KpiCard
                v-for="metric in data.kpis"
                :key="metric.id"
                :metric="metric"
            />
        </m.section>

        <m.div
            class="grid gap-4 lg:grid-cols-3"
            :variants="groupCascade"
            initial="hidden"
            animate="visible"
        >
            <RevenuePanel :points="data.revenue" class="lg:col-span-2" />
            <PipelinePanel :stages="data.pipeline" />
        </m.div>

        <m.div
            class="grid gap-4 lg:grid-cols-2"
            :variants="groupCascade"
            initial="hidden"
            animate="visible"
        >
            <ActivityPanel :entries="data.activity" />
            <UpcomingPanel :items="data.upcoming" />
        </m.div>
    </div>
</template>
