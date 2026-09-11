<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

/**
 * The lifecycle axis of an invoice row: live, or soft-deleted.
 *
 * Deliberately separate from `InvoicePaidBadge` — the two answer different
 * questions and a suspended invoice can perfectly well have been paid. Fusing
 * them into one "status" column is what forces a four-way enum that has to
 * invent a name for "suspended but paid".
 */
const { deletedAt = null } = defineProps<{ deletedAt?: string | null }>();

const isSuspended = computed(() => deletedAt !== null);
</script>

<template>
    <Badge :variant="isSuspended ? 'destructive' : 'secondary'">
        {{ isSuspended ? 'Suspended' : 'Active' }}
    </Badge>
</template>
