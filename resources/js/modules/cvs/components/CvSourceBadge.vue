<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { cvSourcePresentation } from '../helpers/cvPresentation';
import type { CvSource } from '../types';

const { source } = defineProps<{
    /** The `cvs.source` column — which pipeline produced the row. */
    source: CvSource;
}>();

const presentation = computed(() => cvSourcePresentation(source));
</script>

<template>
    <Badge :variant="presentation.variant">
        <component :is="presentation.icon" class="size-3" aria-hidden="true" />
        {{ presentation.label }}
    </Badge>
</template>
