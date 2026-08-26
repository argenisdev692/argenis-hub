<script setup lang="ts">
import { Handshake } from '@lucide/vue';
import { m } from 'motion-v';
import EmptyState from '@/common/feedback/EmptyState.vue';
import { MOTION_DURATION, MOTION_EASE } from '@/lib/motion';
import type { PipelineStage } from '../types';
import DashboardPanel from './DashboardPanel.vue';

const { stages } = defineProps<{
    stages: readonly PipelineStage[];
}>();

/**
 * The bars previously carried `transition-[width]`, which never fired: a CSS
 * transition needs a value to change, and the width is already correct on the
 * first paint. They snapped to their final length and the class was decoration.
 *
 * Driving it from motion gives them a real starting value. Slower than the
 * page's other motion on purpose — this one is showing a quantity, so the
 * growth is the information, not the flourish.
 */
const BAR_TRANSITION = {
    duration: MOTION_DURATION.slow,
    ease: MOTION_EASE,
};
</script>

<template>
    <DashboardPanel title="Pipeline" description="Open deals by stage">
        <ul v-if="stages.length" class="space-y-5">
            <li v-for="stage in stages" :key="stage.id" class="space-y-2">
                <div class="flex items-baseline justify-between gap-3 text-sm">
                    <span class="font-medium">{{ stage.label }}</span>
                    <span class="text-muted-foreground tabular-nums">
                        {{ stage.count }} ·
                        <span class="text-foreground">{{ stage.value }}</span>
                    </span>
                </div>

                <!-- Native progress semantics rather than a styled div, so the
                     share is announced instead of being purely visual. -->
                <div
                    class="h-2 w-full overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    :aria-valuenow="stage.share"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="`${stage.label}: ${stage.share}% of pipeline`"
                >
                    <m.div
                        class="h-full rounded-full bg-brand-gradient"
                        :initial="{ width: '0%' }"
                        :animate="{ width: `${stage.share}%` }"
                        :transition="BAR_TRANSITION"
                    />
                </div>
            </li>
        </ul>

        <EmptyState
            v-else
            :icon="Handshake"
            title="No open deals"
            description="Deals move through these stages as you work them."
        />
    </DashboardPanel>
</template>
