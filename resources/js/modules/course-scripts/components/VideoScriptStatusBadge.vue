<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { scriptStatusPresentation } from '../helpers/coursePresentation';
import type { VideoScriptStatus } from '../types';

const { status } = defineProps<{
    status: VideoScriptStatus;
}>();

const presentation = computed(() => scriptStatusPresentation(status));
</script>

<template>
    <Badge :variant="presentation.variant">
        <component :is="presentation.icon" class="size-3" aria-hidden="true" />
        {{ presentation.label }}
    </Badge>
</template>
