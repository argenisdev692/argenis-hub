<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { socialMediaStatusPresentation } from '../helpers/socialMediaPresentation';

const { status, deletedAt = null } = defineProps<{
    status: string;
    /** Non-null wins over `status` — a suspended package is suspended, first. */
    deletedAt?: string | null;
}>();

const presentation = computed(() =>
    socialMediaStatusPresentation(status, deletedAt),
);

/** The one status that is a process rather than a state gets a spinner. */
const isGenerating = computed(
    () => deletedAt === null && status === 'generating',
);
</script>

<template>
    <Badge :variant="presentation.variant">
        <component
            :is="presentation.icon"
            class="size-3"
            :class="isGenerating && 'animate-spin motion-reduce:animate-none'"
            aria-hidden="true"
        />
        {{ presentation.label }}
    </Badge>
</template>
