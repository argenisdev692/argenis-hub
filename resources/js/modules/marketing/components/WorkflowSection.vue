<script setup lang="ts">
import { m } from 'motion-v';
import { MOTION_STAGGER, REVEAL_ITEM, staggerContainer } from '@/lib/motion';
import { STEPS } from '../content';
import LandingSection from './LandingSection.vue';

/**
 * Three steps that describe an order, so they arrive in that order and slower
 * than the default — the stagger is carrying the meaning here, not just
 * softening the entrance.
 */
const stepCascade = staggerContainer(MOTION_STAGGER * 2);
</script>

<template>
    <LandingSection
        id="workflow"
        eyebrow="How it runs"
        title="Create, convert, collect"
        lede="The modules are wired in that order on purpose: a post feeds a campaign, a campaign feeds a booking, a booking feeds an invoice — no re-typing between them."
    >
        <m.ol
            class="grid gap-4 md:grid-cols-3"
            :variants="stepCascade"
            initial="hidden"
            while-in-view="visible"
        >
            <m.li
                v-for="(step, index) in STEPS"
                :key="step.id"
                class="relative flex flex-col gap-4 rounded-2xl border border-glass-border bg-surface-glass p-6 backdrop-blur-sm"
                :variants="REVEAL_ITEM"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-primary/12"
                    >
                        <component
                            :is="step.icon"
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                    </span>
                    <span
                        class="font-mono text-sm text-muted-foreground tabular-nums"
                    >
                        {{ String(index + 1).padStart(2, '0') }}
                    </span>
                </div>

                <div class="space-y-2">
                    <h3 class="font-semibold tracking-tight">
                        {{ step.title }}
                    </h3>
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{ step.description }}
                    </p>
                </div>
            </m.li>
        </m.ol>
    </LandingSection>
</template>
