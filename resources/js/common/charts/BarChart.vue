<script setup lang="ts">
import { m } from 'motion-v';
import { computed } from 'vue';
import {
    MOTION_DURATION,
    MOTION_EASE,
    MOTION_STAGGER_TIGHT,
} from '@/lib/motion';
import { cn } from '@/lib/utils';

export type BarChartPoint = {
    label: string;
    value: number;
};

/**
 * A categorical bar chart built from layout, not from a canvas.
 *
 * Chart.js would need a resize observer and a re-render on every theme switch
 * to stay correct; plain elements inherit the tokens and reflow on their own,
 * weigh nothing, and stay selectable and screen-readable.
 *
 * Accessibility: the graphic is one labelled `img`, and the same numbers are
 * repeated in a visually hidden table so assistive tech gets the data rather
 * than a shape.
 */
const {
    points,
    caption,
    formatValue = (value: number) => String(value),
    class: className,
} = defineProps<{
    points: readonly BarChartPoint[];
    /** Sentence describing the series, used as the chart's accessible name. */
    caption: string;
    formatValue?: (value: number) => string;
    class?: string;
}>();

const maxValue = computed(() =>
    points.length ? Math.max(...points.map((point) => point.value)) : 0,
);

/**
 * Bars are sized against the maximum, with a 4% floor so a near-zero period
 * still renders something clickable rather than disappearing.
 */
function heightPercent(value: number): string {
    if (maxValue.value === 0) {
        return '4%';
    }

    return `${Math.max(4, (value / maxValue.value) * 100)}%`;
}

const peak = computed(() =>
    points.reduce<BarChartPoint | null>(
        (best, point) => (!best || point.value > best.value ? point : best),
        null,
    ),
);

const accessibleName = computed(() =>
    peak.value
        ? `${caption}. Peak ${formatValue(peak.value.value)} in ${peak.value.label}.`
        : caption,
);

/**
 * Bars grow on `scaleY` from a bottom origin rather than on `height`.
 *
 * Height would be the literal translation, and it would relayout the whole row
 * on every frame of every bar; `scaleY` runs on the compositor. The cost is
 * that `rounded-t-md` is squashed while a bar is mid-growth — six pixels of
 * radius, for a fraction of a second, on a shape that is arriving. Not a
 * trade worth a dozen layout passes.
 */
const barCascade = {
    hidden: {},
    visible: {
        transition: { staggerChildren: MOTION_STAGGER_TIGHT },
    },
};

const barGrow = {
    hidden: { scaleY: 0 },
    visible: {
        scaleY: 1,
        transition: {
            duration: MOTION_DURATION.slow,
            ease: MOTION_EASE,
        },
    },
};
</script>

<template>
    <figure :class="cn('flex flex-col gap-3', className)">
        <m.div
            class="flex h-48 items-end gap-1.5 sm:gap-2"
            role="img"
            :aria-label="accessibleName"
            :variants="barCascade"
            initial="hidden"
            animate="visible"
        >
            <div
                v-for="point in points"
                :key="point.label"
                class="group relative flex h-full flex-1 flex-col justify-end"
            >
                <!-- Value on hover / focus. `pointer-events-none` keeps it from
                     stealing the hover it depends on. -->
                <span
                    class="pointer-events-none absolute inset-x-0 -top-1 z-10 mx-auto w-fit rounded-md border border-glass-border bg-popover px-2 py-1 text-xs font-medium text-popover-foreground tabular-nums opacity-0 shadow-soft transition-opacity duration-150 group-hover:opacity-100"
                >
                    {{ formatValue(point.value) }}
                </span>

                <m.div
                    class="w-full origin-bottom rounded-t-md bg-brand-gradient opacity-80 transition-opacity duration-200 ease-brand group-hover:opacity-100"
                    :style="{ height: heightPercent(point.value) }"
                    :variants="barGrow"
                />
            </div>
        </m.div>

        <div
            class="flex gap-1.5 text-center text-xs text-muted-foreground sm:gap-2"
            aria-hidden="true"
        >
            <span
                v-for="point in points"
                :key="point.label"
                class="flex-1 truncate"
            >
                {{ point.label }}
            </span>
        </div>

        <!-- The same series as data, for assistive technology. -->
        <table class="sr-only">
            <caption>
                {{
                    caption
                }}
            </caption>
            <thead>
                <tr>
                    <th scope="col">Period</th>
                    <th scope="col">Value</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="point in points" :key="point.label">
                    <th scope="row">{{ point.label }}</th>
                    <td>{{ formatValue(point.value) }}</td>
                </tr>
            </tbody>
        </table>
    </figure>
</template>
