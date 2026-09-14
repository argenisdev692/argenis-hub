<script setup lang="ts">
import { cvLabel, formatDate } from '../helpers/cvPresentation';
import type { Cv } from '../types';

/**
 * The CVs a confirmation acts on — label, filename and upload date — so the
 * operator confirms the rows they picked, not a bare count. Shared by the
 * single and bulk suspend/restore modals; scrolls past a handful of rows.
 */
const { cvs } = defineProps<{
    cvs: readonly Cv[];
}>();
</script>

<template>
    <ul
        class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
    >
        <li
            v-for="cv in cvs"
            :key="cv.uuid"
            class="flex items-baseline justify-between gap-3"
        >
            <span class="flex min-w-0 flex-col">
                <span class="truncate font-medium">{{ cvLabel(cv) }}</span>
                <span class="truncate text-xs text-muted-foreground">
                    {{ cv.original_filename }}
                </span>
            </span>
            <span class="shrink-0 text-muted-foreground">
                {{ formatDate(cv.created_at) }}
            </span>
        </li>
    </ul>
</template>
