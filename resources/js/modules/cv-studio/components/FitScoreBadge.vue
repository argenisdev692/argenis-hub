<script setup lang="ts">
import { computed } from 'vue';
import { bandLabel } from '../helpers/studioPresentation';

const {
    score = null,
    band = null,
} = defineProps<{
    score?: number | null;
    band?: string | null;
}>();

const tone = computed(() => {
    switch (band) {
        case 'strong':
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
        case 'good':
            return 'bg-sky-500/10 text-sky-600 dark:text-sky-400';
        case 'apply':
            return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
        case 'consider':
            return 'bg-orange-500/10 text-orange-600 dark:text-orange-400';
        default:
            return 'bg-muted text-muted-foreground';
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="tone"
    >
        <span v-if="score !== null" class="tabular-nums">{{ Math.round(score) }}</span>
        <span>{{ bandLabel(band) }}</span>
    </span>
</template>
