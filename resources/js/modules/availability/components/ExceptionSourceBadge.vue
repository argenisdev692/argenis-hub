<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { exceptionSourcePresentation } from '../helpers/availabilityPresentation';
import type { ExceptionSource } from '../types';

/**
 * Where the exception came from.
 *
 * Worth a column of its own because the two provenances behave differently: a
 * `holiday` row is rebuilt by `HolidayMaterializer` on the yearly sync and on a
 * country change, so an edit to one is discarded. The badge is the warning the
 * operator gets before spending the effort.
 */
const { source } = defineProps<{
    source: ExceptionSource;
}>();

const presentation = computed(() => exceptionSourcePresentation(source));
</script>

<template>
    <Badge :variant="presentation.variant">
        <component :is="presentation.icon" class="size-3" aria-hidden="true" />
        {{ presentation.label }}
    </Badge>
</template>
