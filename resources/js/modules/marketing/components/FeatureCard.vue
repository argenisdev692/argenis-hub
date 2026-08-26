<script setup lang="ts">
import { Check } from '@lucide/vue';
import { m } from 'motion-v';
import { computed } from 'vue';
import { INTERACTIVE_SPRING, REVEAL_SCALE } from '@/lib/motion';
import { cn } from '@/lib/utils';
import { bentoSpanClass } from '../helpers/bentoSpan';
import { toneClasses } from '../helpers/toneClasses';
import type { MarketingFeature } from '../types';

const { feature } = defineProps<{
    feature: MarketingFeature;
}>();

const tone = computed(() => toneClasses(feature.tone));
const span = computed(() => bentoSpanClass(feature.span));

/**
 * The hover lift moved from CSS to a spring, and it had to.
 *
 * motion-v writes `transform` as an inline style on every frame, which beats
 * `hover:-translate-y-0.5` in the cascade — the class would simply stop working
 * the moment this card became a motion element. Worse, leaving `transform` in
 * the CSS `transition` list would set the two systems tweening the same
 * property at once, which reads as lag rather than as a lift.
 *
 * So `transform` is gone from the transition list (border and shadow still
 * tween in CSS, and neither conflicts) and the lift is a spring instead. The
 * upgrade is free: a spring re-targets from wherever the card currently is, so
 * moving the pointer across a grid of these no longer restarts each animation
 * from zero.
 */
const HOVER_LIFT = -4;
</script>

<template>
    <m.article
        :class="
            cn(
                'group relative flex flex-col gap-4 rounded-2xl border border-glass-border bg-surface-glass p-6 backdrop-blur-sm',
                'transition-[border-color,box-shadow] duration-300 ease-brand',
                'hover:shadow-glow',
                tone.ring,
                span,
            )
        "
        :variants="REVEAL_SCALE"
        :while-hover="{ y: HOVER_LIFT }"
        :transition="INTERACTIVE_SPRING"
    >
        <span
            :class="
                cn(
                    'flex size-11 shrink-0 items-center justify-center rounded-xl',
                    tone.chip,
                )
            "
        >
            <component
                :is="feature.icon"
                :class="cn('size-5', tone.icon)"
                aria-hidden="true"
            />
        </span>

        <div class="space-y-2">
            <h3 class="text-lg font-semibold tracking-tight text-balance">
                {{ feature.title }}
            </h3>
            <p class="text-sm text-pretty text-muted-foreground">
                {{ feature.description }}
            </p>
        </div>

        <ul
            v-if="feature.highlights?.length"
            class="mt-auto grid gap-2 pt-2 text-sm text-muted-foreground"
        >
            <li
                v-for="highlight in feature.highlights"
                :key="highlight"
                class="flex items-start gap-2"
            >
                <Check
                    class="mt-0.5 size-4 shrink-0 text-success"
                    aria-hidden="true"
                />
                {{ highlight }}
            </li>
        </ul>
    </m.article>
</template>
