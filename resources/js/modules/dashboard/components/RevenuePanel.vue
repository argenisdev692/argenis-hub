<script setup lang="ts">
import { computed } from 'vue';
import BarChart from '@/common/charts/BarChart.vue';
import type { BarChartPoint } from '@/common/charts/BarChart.vue';
import EmptyState from '@/common/feedback/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { formatCompactCurrency, formatCurrency } from '../helpers/format';
import type { RevenuePoint } from '../types';
import DashboardPanel from './DashboardPanel.vue';

const { points } = defineProps<{
    points: readonly RevenuePoint[];
}>();

const chartPoints = computed<BarChartPoint[]>(() =>
    points.map((point) => ({ label: point.label, value: point.amount })),
);

const total = computed(() =>
    points.reduce((sum, point) => sum + point.amount, 0),
);
</script>

<template>
    <DashboardPanel
        title="Invoiced revenue"
        description="Last 10 months, all clients"
    >
        <template #action>
            <Badge variant="secondary" class="tabular-nums">
                {{ formatCurrency(total) }} total
            </Badge>
        </template>

        <BarChart
            v-if="chartPoints.length"
            :points="chartPoints"
            caption="Invoiced revenue by month"
            :format-value="formatCompactCurrency"
        />

        <EmptyState
            v-else
            title="No revenue yet"
            description="Once an invoice is marked paid it will show up here."
        />
    </DashboardPanel>
</template>
