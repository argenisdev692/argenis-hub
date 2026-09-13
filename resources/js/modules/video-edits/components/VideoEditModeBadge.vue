<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { videoEditModePresentation } from '../helpers/videoEditPresentation';
import type { VideoEditMode } from '../types';

const { mode } = defineProps<{ mode: VideoEditMode }>();

const presentation = computed(() => videoEditModePresentation(mode));
</script>

<template>
    <Badge :variant="presentation.variant">
        <component :is="presentation.icon" class="size-3" aria-hidden="true" />
        {{ presentation.label }}
    </Badge>
</template>
