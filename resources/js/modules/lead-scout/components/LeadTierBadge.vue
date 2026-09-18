<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { tierSeverity } from '../helpers/leadPresentation';

const { tier } = defineProps<{
    tier: string | null;
}>();
</script>

<template>
    <Badge :variant="tierSeverity(tier)">
        {{ tier === null ? 'Unscored' : tier === 'discarded' ? 'Discarded' : `Tier ${tier}` }}
    </Badge>
</template>
