<script setup lang="ts">
import { m } from 'motion-v';
import { MOTION_STAGGER, REVEAL_FADE, staggerContainer } from '@/lib/motion';
import { METRICS } from '../content';
import LandingSection from './LandingSection.vue';

/**
 * Deliberately a plain reveal, not a count-up.
 *
 * A rolling number only earns its keep when the number is large enough that
 * watching it climb tells you something. These are `5`, `100%`, `2` and `AA` —
 * two of them are single digits, and the last one has no numeric value at all.
 * Counting to five is a gimmick; animating "AA" is impossible. If these ever
 * become live figures (`MarketingMetric.value` is typed `string` precisely
 * because they are positioning claims, not data), a count-up becomes worth
 * revisiting.
 *
 * The cells are separated by a 1px grid gap showing the border colour beneath,
 * so they reveal in place — travel would open gaps in that hairline mid-flight.
 */
const metricCascade = staggerContainer(MOTION_STAGGER);
</script>

<template>
    <LandingSection id="results" class="py-14 lg:py-20">
        <m.dl
            class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-glass-border bg-glass-border lg:grid-cols-4"
            :variants="metricCascade"
            initial="hidden"
            while-in-view="visible"
        >
            <m.div
                v-for="metric in METRICS"
                :key="metric.id"
                class="flex flex-col gap-1 bg-surface-glass p-6 backdrop-blur-sm sm:p-8"
                :variants="REVEAL_FADE"
            >
                <dt class="order-2 text-sm font-medium">
                    {{ metric.label }}
                </dt>
                <dd
                    class="order-1 bg-brand-gradient bg-clip-text text-3xl font-semibold tracking-tight text-transparent tabular-nums sm:text-4xl"
                >
                    {{ metric.value }}
                </dd>
                <dd class="order-3 text-xs text-muted-foreground">
                    {{ metric.caption }}
                </dd>
            </m.div>
        </m.dl>
    </LandingSection>
</template>
