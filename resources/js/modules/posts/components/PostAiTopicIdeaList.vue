<script setup lang="ts">
import { SparklesIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { scoreTextClass, scoreTone } from '../helpers/postPresentation';
import type { PostTopicIdea } from '../types';

/**
 * The ideation results — presentational only. Picking one does not spend a
 * generation call; it fills the brief, and the panel above decides what to do
 * with it. Keeping that split is what lets a user audition five topics for
 * free and pay once.
 */

const { ideas, selectedTitle = null } = defineProps<{
    ideas: readonly PostTopicIdea[];
    /** Highlights whichever idea currently backs the brief. */
    selectedTitle?: string | null;
}>();

const emit = defineEmits<{
    select: [idea: PostTopicIdea];
}>();

const METRICS = [
    { key: 'estimated_virality', label: 'Virality' },
    { key: 'estimated_roi', label: 'ROI' },
    { key: 'eeat_potential', label: 'E-E-A-T' },
] as const;
</script>

<template>
    <ul class="flex flex-col gap-3">
        <li
            v-for="idea in ideas"
            :key="idea.title"
            :class="
                cn(
                    'rounded-lg border p-3 transition-colors',
                    selectedTitle === idea.title
                        ? 'border-primary bg-primary/5'
                        : 'border-border bg-card',
                )
            "
        >
            <div class="flex items-start justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <p class="text-sm font-medium">{{ idea.title }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ idea.hook }}
                    </p>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="shrink-0"
                    @click="emit('select', idea)"
                >
                    <SparklesIcon class="size-4" aria-hidden="true" />
                    Use
                </Button>
            </div>

            <dl class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                <div
                    v-for="metric in METRICS"
                    :key="metric.key"
                    class="flex items-baseline gap-1.5"
                >
                    <dt class="text-[11px] text-muted-foreground">
                        {{ metric.label }}
                    </dt>
                    <dd
                        :class="
                            cn(
                                'text-xs font-semibold tabular-nums',
                                scoreTextClass(scoreTone(idea[metric.key])),
                            )
                        "
                    >
                        {{ idea[metric.key] }}
                    </dd>
                </div>

                <Badge variant="secondary" class="ms-auto">
                    {{ idea.key_trend }}
                </Badge>
            </dl>

            <p class="mt-2 text-xs text-muted-foreground">
                <span class="font-medium text-foreground">Angle:</span>
                {{ idea.angle }} — {{ idea.why_it_works }}
            </p>
        </li>
    </ul>
</template>
