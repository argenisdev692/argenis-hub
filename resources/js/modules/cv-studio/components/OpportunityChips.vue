<script setup lang="ts">
/**
 * Opportunity factors with evidence grade + source tooltip (NFR-14).
 * Every factor is an estimate with its sample size — never a statistic.
 */
export type OpportunityComponent = {
    value: number;
    grade: string;
    source: string;
    n: number;
    k: number;
    reason: string;
};

const { components = {} } = defineProps<{
    components?: Record<string, OpportunityComponent>;
}>();

const entries = Object.entries(components);
</script>

<template>
    <div v-if="entries.length > 0" class="flex flex-wrap gap-1.5">
        <span
            v-for="[name, component] of entries"
            :key="name"
            class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground"
            :title="`${component.reason} Grade ${component.grade} · ${component.source} (n=${component.n}, k=${component.k})`"
        >
            {{ name }} {{ component.value.toFixed(2) }}
            <span class="opacity-60">· {{ component.grade }}</span>
        </span>
    </div>
    <p v-else class="text-xs text-muted-foreground">
        Opportunity layer neutral — pure fit order.
    </p>
</template>
