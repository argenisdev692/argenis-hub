<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { videoEditStatusPresentation } from '../helpers/videoEditPresentation';
import type { VideoEditStatus } from '../types';

const { status, progressPercent = null } = defineProps<{
    status: VideoEditStatus;
    /** Shown beside "Processing" so the table reads as live. */
    progressPercent?: number | null;
}>();

const presentation = computed(() => videoEditStatusPresentation(status));
const isSpinning = computed(() => status === 'processing');
const label = computed(() =>
    isSpinning.value && progressPercent !== null
        ? `${presentation.value.label} · ${progressPercent}%`
        : presentation.value.label,
);
</script>

<template>
    <Badge :variant="presentation.variant">
        <component
            :is="presentation.icon"
            :class="[
                'size-3',
                isSpinning && 'animate-spin motion-reduce:animate-none',
            ]"
            aria-hidden="true"
        />
        <span class="tabular-nums">{{ label }}</span>
    </Badge>
</template>
