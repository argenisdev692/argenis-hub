<script setup lang="ts">
import { cn } from '@/lib/utils';
import { bandLabel, bandTone } from '../helpers/studioPresentation';

const { score = null, band = null } = defineProps<{
    score?: number | null;
    band?: string | null;
}>();
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                bandTone(band),
            )
        "
    >
        <span v-if="score !== null" class="tabular-nums">{{
            Math.round(score)
        }}</span>
        <span>{{ bandLabel(band) }}</span>
    </span>
</template>
