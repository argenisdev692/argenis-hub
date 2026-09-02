<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { cvNichePresentation } from '../helpers/cvPresentation';
import type { CvNiche } from '../types';

const { niche } = defineProps<{
    /** The `cvs.niche` column — the axis Module 2's RAG pipeline branches on. */
    niche: CvNiche;
}>();

const presentation = computed(() => cvNichePresentation(niche));
</script>

<template>
    <Badge :variant="presentation.variant">
        <component :is="presentation.icon" class="size-3" aria-hidden="true" />
        {{ presentation.label }}
    </Badge>
</template>
