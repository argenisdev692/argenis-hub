<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { backupStatusPresentation } from '@/modules/backups/helpers/backupStatusPresentation';

const { status } = defineProps<{ status: string }>();

const presentation = computed(() => backupStatusPresentation(status));
</script>

<template>
    <Badge :variant="presentation.variant">
        <component :is="presentation.icon" class="size-3" aria-hidden="true" />
        {{ presentation.label }}
    </Badge>
</template>
