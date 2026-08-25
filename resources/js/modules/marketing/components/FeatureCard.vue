<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { bentoSpanClass } from '../helpers/bentoSpan';
import { toneClasses } from '../helpers/toneClasses';
import type { MarketingFeature } from '../types';

const { feature } = defineProps<{
    feature: MarketingFeature;
}>();

const tone = computed(() => toneClasses(feature.tone));
const span = computed(() => bentoSpanClass(feature.span));
</script>

<template>
    <article
        :class="
            cn(
                'group relative flex flex-col gap-4 rounded-2xl border border-glass-border bg-surface-glass p-6 backdrop-blur-sm',
                'transition-[border-color,transform,box-shadow] duration-300 ease-brand',
                'hover:-translate-y-0.5 hover:shadow-glow',
                tone.ring,
                span,
            )
        "
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
    </article>
</template>
