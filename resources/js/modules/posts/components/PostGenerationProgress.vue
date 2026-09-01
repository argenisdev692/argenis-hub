<script setup lang="ts">
import { CheckIcon, Loader2Icon, SparklesIcon } from '@lucide/vue';
import { computed } from 'vue';
import { Progress } from '@/components/ui/progress';
import {
    POST_GENERATION_PHASES,
    type PostAiGeneration,
    type PostGenerationPhase,
} from '../types';

/**
 * The live phase list for a background draft run.
 *
 * Reads `generation.status` — the same enum the pipeline writes on the row —
 * and renders every phase as done / running / pending against it. The ORDER
 * lives in `POST_GENERATION_PHASES` beside the type, so a phase added to the
 * backend enum shows up here as a compile error rather than a silently missing
 * row.
 *
 * `judging` is worth naming out loud rather than folding into "writing": it is
 * a second, independent model scoring the draft, and it is the reason a run
 * takes as long as it does.
 */
const { generation } = defineProps<{
    generation: PostAiGeneration;
}>();

/** Phases before the current one are done; `completed` finishes all of them. */
const currentIndex = computed(() => {
    if (generation.status === 'completed') {
        return POST_GENERATION_PHASES.length;
    }

    return POST_GENERATION_PHASES.indexOf(
        generation.status as PostGenerationPhase,
    );
});

function stateOf(index: number): 'done' | 'running' | 'pending' {
    if (currentIndex.value > index) {
        return 'done';
    }

    return currentIndex.value === index ? 'running' : 'pending';
}

const PHASE_LABELS: Record<PostGenerationPhase, string> = {
    researching: 'Researching sources',
    writing: 'Writing content',
    judging: 'Quality review',
    generating_image: 'Generating visual',
};

/**
 * The loop re-runs the first three phases per attempt, so the iteration
 * counter is the difference between "stuck" and "on attempt 3 of 5".
 */
const showsIteration = computed(
    () => generation.iteration > 1 && !generation.is_terminal,
);
</script>

<template>
    <div
        class="flex flex-col gap-4 rounded-lg border border-border bg-card p-4"
        role="status"
        aria-live="polite"
    >
        <div class="flex items-center gap-2">
            <SparklesIcon
                class="size-4 text-primary motion-safe:animate-pulse"
                aria-hidden="true"
            />
            <p class="text-sm font-medium text-foreground">
                Argenis AI is working
            </p>
            <span
                v-if="showsIteration"
                class="ml-auto text-xs text-muted-foreground tabular-nums"
            >
                Attempt {{ generation.iteration }} of
                {{ generation.max_iterations }}
            </span>
        </div>

        <Progress :model-value="generation.progress" class="h-1.5" />

        <ul class="flex flex-col gap-2">
            <li
                v-for="(phase, index) in POST_GENERATION_PHASES"
                :key="phase"
                class="flex items-center gap-2 text-sm"
                :class="
                    stateOf(index) === 'pending'
                        ? 'text-muted-foreground'
                        : 'text-foreground'
                "
            >
                <CheckIcon
                    v-if="stateOf(index) === 'done'"
                    class="size-4 shrink-0 text-success"
                    aria-hidden="true"
                />
                <Loader2Icon
                    v-else-if="stateOf(index) === 'running'"
                    class="size-4 shrink-0 text-primary motion-safe:animate-spin"
                    aria-hidden="true"
                />
                <span
                    v-else
                    class="size-4 shrink-0 rounded-full border border-input"
                    aria-hidden="true"
                />

                <span>{{ PHASE_LABELS[phase] }}</span>

                <span class="sr-only">
                    {{
                        stateOf(index) === 'done'
                            ? 'done'
                            : stateOf(index) === 'running'
                              ? 'in progress'
                              : 'pending'
                    }}
                </span>
            </li>
        </ul>

        <p
            v-if="generation.stage_message"
            class="text-xs text-muted-foreground"
        >
            {{ generation.stage_message }}
        </p>
    </div>
</template>
