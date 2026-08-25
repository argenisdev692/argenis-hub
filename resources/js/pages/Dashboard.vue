<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Download, Plus } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
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
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
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
        </header>

        <!-- Four tiles, deliberately: the metrics people check daily stay one
             glance away instead of competing with a wall of widgets. -->
        <section
            aria-label="Key metrics"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <KpiCard
                v-for="metric in data.kpis"
                :key="metric.id"
                :metric="metric"
            />
        </section>

        <div class="grid gap-4 lg:grid-cols-3">
            <RevenuePanel :points="data.revenue" class="lg:col-span-2" />
            <PipelinePanel :stages="data.pipeline" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <ActivityPanel :entries="data.activity" />
            <UpcomingPanel :items="data.upcoming" />
        </div>
    </div>
</template>
