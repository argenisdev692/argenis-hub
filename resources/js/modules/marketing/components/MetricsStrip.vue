<script setup lang="ts">
import { METRICS } from '../content';
import LandingSection from './LandingSection.vue';
</script>

<template>
    <LandingSection id="results" class="py-14 lg:py-20">
        <dl
            class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-glass-border bg-glass-border lg:grid-cols-4"
        >
            <div
                v-for="metric in METRICS"
                :key="metric.id"
                class="flex flex-col gap-1 bg-surface-glass p-6 backdrop-blur-sm sm:p-8"
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
            </div>
        </dl>
    </LandingSection>
</template>
