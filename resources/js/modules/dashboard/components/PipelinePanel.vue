<script setup lang="ts">
import { Handshake } from '@lucide/vue';
import EmptyState from '@/common/feedback/EmptyState.vue';
import type { PipelineStage } from '../types';
import DashboardPanel from './DashboardPanel.vue';

const { stages } = defineProps<{
    stages: readonly PipelineStage[];
}>();
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
                    <div
                        class="h-full rounded-full bg-brand-gradient transition-[width] duration-500 ease-brand"
                        :style="{ width: `${stage.share}%` }"
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
