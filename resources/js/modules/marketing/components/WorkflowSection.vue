<script setup lang="ts">
import { STEPS } from '../content';
import LandingSection from './LandingSection.vue';
</script>

<template>
    <LandingSection
        id="workflow"
        eyebrow="How it runs"
        title="Set it up once, then stop administering it"
        lede="The hub is opinionated on purpose: fewer decisions to make, fewer places for the truth to drift apart."
    >
        <ol class="grid gap-4 md:grid-cols-3">
            <li
                v-for="(step, index) in STEPS"
                :key="step.id"
                class="relative flex flex-col gap-4 rounded-2xl border border-glass-border bg-surface-glass p-6 backdrop-blur-sm"
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
            </li>
        </ol>
    </LandingSection>
</template>
