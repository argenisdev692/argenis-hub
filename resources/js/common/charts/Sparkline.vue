<script setup lang="ts">
import { computed, useId } from 'vue';
import { cn } from '@/lib/utils';

/**
 * A dependency-free trend line.
 *
 * Draws in a fixed 100×32 viewBox and stretches with `preserveAspectRatio`, so
 * one instance works at KPI-tile size and at card width without recomputing.
 *
 * `stroke` takes a CSS colour *expression* rather than a class, because SVG
 * paint cannot be set by a Tailwind text utility on the polyline itself —
 * always pass a token (`var(--chart-1)`), never a literal colour.
 */
const {
    values,
    stroke = 'var(--chart-1)',
    filled = true,
    label,
    class: className,
} = defineProps<{
    values: readonly number[];
    stroke?: string;
    filled?: boolean;
    /** Provide when the line carries meaning on its own; omit when a nearby
     *  value already states it, and the graphic stays decorative. */
    label?: string;
    class?: string;
}>();

const VIEW_WIDTH = 100;
const VIEW_HEIGHT = 32;
/** Keeps the stroke from being clipped at the extremes. */
const PADDING = 2;

const gradientId = useId();

const points = computed<string>(() => {
    if (values.length < 2) {
        return '';
    }

    const min = Math.min(...values);
    const max = Math.max(...values);
    const span = max - min;
    const usableHeight = VIEW_HEIGHT - PADDING * 2;

    return values
        .map((value, index) => {
            const x = (index / (values.length - 1)) * VIEW_WIDTH;
            // A flat series has no span to normalise against; centre it
            // instead of dividing by zero.
            const ratio = span === 0 ? 0.5 : (value - min) / span;
            const y = VIEW_HEIGHT - PADDING - ratio * usableHeight;

            return `${x.toFixed(2)},${y.toFixed(2)}`;
        })
        .join(' ');
});

/** The line, closed along the baseline, so it can be filled. */
const areaPoints = computed<string>(() =>
    points.value
        ? `0,${VIEW_HEIGHT} ${points.value} ${VIEW_WIDTH},${VIEW_HEIGHT}`
        : '',
);
</script>

<template>
    <svg
        v-if="points"
        :class="cn('h-8 w-full overflow-visible', className)"
        :viewBox="`0 0 ${VIEW_WIDTH} ${VIEW_HEIGHT}`"
        preserveAspectRatio="none"
        :role="label ? 'img' : 'presentation'"
        :aria-label="label"
        :aria-hidden="label ? undefined : true"
        focusable="false"
    >
        <defs v-if="filled">
            <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="stroke" stop-opacity="0.28" />
                <stop offset="100%" :stop-color="stroke" stop-opacity="0" />
            </linearGradient>
        </defs>

        <polygon
            v-if="filled"
            :points="areaPoints"
            :fill="`url(#${gradientId})`"
        />

        <polyline
            :points="points"
            fill="none"
            :stroke="stroke"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            vector-effect="non-scaling-stroke"
        />
    </svg>
</template>
