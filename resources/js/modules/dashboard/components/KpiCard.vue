<script setup lang="ts">
import { computed } from 'vue';
import Sparkline from '@/common/charts/Sparkline.vue';
import { cn } from '@/lib/utils';
import { toneClasses } from '@/modules/marketing/helpers/toneClasses';
import { formatSignedPercent } from '../helpers/format';
import { trendPresentation } from '../helpers/trend';
import type { KpiMetric } from '../types';

const { metric } = defineProps<{
    metric: KpiMetric;
}>();

const tone = computed(() => toneClasses(metric.tone));
const trend = computed(() =>
    trendPresentation(metric.changePercent, metric.polarity),
);
const change = computed(() => formatSignedPercent(metric.changePercent));

/**
 * The sparkline takes the same colour as the delta, so a tile that is trending
 * badly reads as such at a glance rather than staying brand-coloured.
 */
const sparkStroke = computed(() => {
    if (trend.value.direction === 'flat') {
        return 'var(--muted-foreground)';
    }

    return trend.value.class === 'text-success'
        ? 'var(--success)'
        : 'var(--destructive)';
});
</script>

<template>
    <article
        class="group flex flex-col gap-4 rounded-xl border border-glass-border bg-surface-glass p-5 backdrop-blur-sm transition-shadow duration-200 ease-brand hover:shadow-soft"
    >
        <header class="flex items-start justify-between gap-3">
            <h3 class="text-sm font-medium text-muted-foreground">
                {{ metric.label }}
            </h3>

            <span
                :class="
                    cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                        tone.chip,
                    )
                "
            >
                <component
                    :is="metric.icon"
                    :class="cn('size-4.5', tone.icon)"
                    aria-hidden="true"
                />
            </span>
        </header>

        <p
            class="text-3xl font-semibold tracking-tight tabular-nums"
            data-numeric
        >
            {{ metric.value }}
        </p>

        <footer class="mt-auto flex items-end justify-between gap-4">
            <p :class="cn('flex items-center gap-1.5 text-sm', trend.class)">
                <component :is="trend.icon" class="size-4" aria-hidden="true" />
                <span class="font-medium tabular-nums">{{ change }}</span>
                <span class="sr-only">{{ trend.srLabel }},</span>
                <span class="text-muted-foreground">
                    {{ metric.comparison }}
                </span>
            </p>

            <Sparkline
                :values="metric.series"
                :stroke="sparkStroke"
                class="h-8 w-20 shrink-0"
            />
        </footer>
    </article>
</template>
