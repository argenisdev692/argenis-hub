<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { safeExternalUrl } from '@/lib/utils';
import type { LeadScoreReason } from '../types';

const { reasons, subscores } = defineProps<{
    reasons: LeadScoreReason[];
    subscores: Record<string, number>;
}>();

const ordered = computed(() =>
    [...reasons].sort((a, b) => Math.abs(b.points) - Math.abs(a.points)),
);

function pointsVariant(points: number): 'default' | 'secondary' | 'destructive' {
    if (points > 0) {
        return 'default';
    }

    if (points < 0) {
        return 'destructive';
    }

    return 'secondary';
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Why this score</CardTitle>
            <CardDescription>
                Every reason links to the evidence behind it — facts weigh
                full, inferences weigh less.
            </CardDescription>
        </CardHeader>

        <CardContent class="flex flex-col gap-4">
            <div class="flex flex-wrap gap-2">
                <Badge
                    v-for="(value, dimension) in subscores"
                    :key="dimension"
                    variant="secondary"
                >
                    {{ dimension }}: {{ value }}
                </Badge>
            </div>

            <ul class="flex flex-col gap-3">
                <li
                    v-for="(reason, index) in ordered"
                    :key="`${reason.signal_key ?? 'gap'}-${index}`"
                    class="flex flex-col gap-1 rounded-lg border border-border p-3"
                >
                    <div class="flex items-center gap-2">
                        <Badge :variant="pointsVariant(reason.points)">
                            {{ reason.points > 0 ? `+${reason.points}` : reason.points }}
                        </Badge>
                        <span class="text-sm">
                            {{ reason.explanation }}
                        </span>
                    </div>

                    <p
                        v-if="reason.evidence_excerpt"
                        class="text-xs text-muted-foreground"
                    >
                        “{{ reason.evidence_excerpt }}”
                    </p>

                    <a
                        v-if="safeExternalUrl(reason.evidence_url) !== null"
                        :href="safeExternalUrl(reason.evidence_url) ?? undefined"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-xs text-primary underline"
                    >
                        {{ reason.evidence_url }}
                    </a>
                </li>
            </ul>

            <p
                v-if="ordered.length === 0"
                class="text-sm text-muted-foreground"
            >
                No score computed yet — run a re-score first.
            </p>
        </CardContent>
    </Card>
</template>
