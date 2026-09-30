<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { useRunStatus } from '@/modules/cv-studio/composables/useRuns';
import { formatMicros } from '@/modules/cv-studio/helpers/studioPresentation';
import { index as postingsIndex } from '@/routes/cv-studio/postings';
import { report } from '@/routes/cv-studio/runs';

const { uuid } = defineProps<{ uuid: string }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'CV Studio', href: postingsIndex() },
            { title: 'Run' },
        ],
    },
});

/**
 * `isPending` is the first load only; `isLoading` is true for every fetch,
 * including the 3s poll. Gating the panel on `isLoading` unmounted and
 * remounted the whole sequence on each poll — a skeleton that flickers is worse
 * than no skeleton. The panel now stays mounted and the poll only drives a
 * quiet "updating" hint.
 */
const { run, isPending, isLoading } = useRunStatus(uuid);

/**
 * A poll in flight while the run is already on screen. `isLoading` covers the
 * first load too, so the first load is excluded — that difference is the whole
 * fix for a panel that blinks every 3 seconds.
 */
const isBackgroundRefresh = computed(() => isLoading.value && !isPending.value);

const STAGES = [
    { key: 'queued', label: 'Queued' },
    { key: 'harvesting', label: 'Harvesting sources' },
    { key: 'harvested', label: 'Deduped' },
    { key: 'extracted', label: 'Extracted' },
    { key: 'finished', label: 'Finished' },
] as const;

type StageState = 'done' | 'current' | 'pending';

/**
 * Stages before the current one are done; `finished` marks every stage done.
 * `failed` matches no stage (the backend does not record which one broke),
 * so the stages render pending and the failure banner carries the message.
 */
const currentIndex = computed(() =>
    STAGES.findIndex((stage) => stage.key === run.value?.status),
);

function stageState(index: number): StageState {
    if (run.value?.status === 'finished') {
        return 'done';
    }

    if (index < currentIndex.value) {
        return 'done';
    }

    return index === currentIndex.value ? 'current' : 'pending';
}

const STAGE_DOT: Record<StageState, string> = {
    done: 'bg-success',
    current: 'bg-primary animate-pulse motion-reduce:animate-none',
    pending: 'bg-muted-foreground/30',
};

const isFailed = computed(() => run.value?.status === 'failed');

const isRunning = computed(
    () => !isFailed.value && run.value?.status !== 'finished',
);

/**
 * Progress is stage-based, and only that: the run counts the funnel
 * (`candidates` → `gate_passed` → `extracted`) but never how far scoring has
 * got — `scored_count` is never written by a job — so a finer percentage would
 * be invented precision that jumps around and loses trust. Each stage counts
 * the same, and a failed run keeps the share it had reached.
 */
const progressPercent = computed(() => {
    if (run.value?.status === 'finished') {
        return 100;
    }

    if (currentIndex.value < 0) {
        return 0;
    }

    return Math.round((currentIndex.value / STAGES.length) * 100);
});

const currentStageLabel = computed(() => {
    if (run.value?.status === 'finished') {
        return 'Finished';
    }

    return currentIndex.value >= 0
        ? STAGES[currentIndex.value].label
        : 'Starting';
});

function openReport(): void {
    router.visit(report(uuid).url);
}
</script>

<template>
    <Head title="CV Studio · Run" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Discovery run</h1>
            <p class="font-mono text-xs text-muted-foreground">{{ uuid }}</p>
        </header>

        <!-- First load only: mirrors the real layout so nothing reflows. -->
        <div v-if="isPending" class="flex flex-col gap-6" aria-busy="true">
            <div class="flex flex-col gap-2">
                <Skeleton class="h-2 w-full rounded-full" />
                <Skeleton class="h-4 w-48" />
            </div>
            <div class="flex flex-col gap-2">
                <Skeleton
                    v-for="stage in STAGES"
                    :key="stage.key"
                    class="h-14 w-full rounded-xl"
                />
            </div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Skeleton
                    v-for="tile in 4"
                    :key="tile"
                    class="h-20 rounded-lg"
                />
            </div>
            <span class="sr-only">Loading run status…</span>
        </div>

        <template v-else-if="run">
            <section
                class="flex flex-col gap-2 rounded-xl border border-border bg-card p-4"
                aria-label="Run progress"
            >
                <div class="flex items-baseline justify-between gap-2">
                    <p class="text-sm font-medium">
                        {{ isFailed ? 'Stopped' : currentStageLabel }}
                    </p>
                    <p
                        v-if="!isFailed"
                        class="text-sm text-muted-foreground tabular-nums"
                    >
                        <span class="font-semibold text-foreground">{{
                            progressPercent
                        }}</span>
                        %
                        <span
                            v-if="isBackgroundRefresh"
                            class="ml-1 text-xs text-muted-foreground"
                        >
                            · updating
                        </span>
                    </p>
                </div>

                <Progress
                    v-if="!isFailed"
                    :model-value="progressPercent"
                    :aria-label="`Run progress: ${progressPercent}%`"
                />

                <p
                    class="text-xs text-muted-foreground"
                    role="status"
                    aria-live="polite"
                >
                    <template v-if="isFailed">
                        The backend does not record which stage broke, so no
                        progress is claimed here. Everything collected before
                        the failure is kept.
                    </template>
                    <template v-else-if="isRunning">
                        Working through the pipeline. This page refreshes every
                        few seconds.
                    </template>
                    <template v-else>
                        Pipeline complete. Open the insight report for what the
                        market asked for.
                    </template>
                </p>
            </section>

            <div
                v-if="isFailed"
                role="alert"
                class="rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive"
            >
                This run failed after its retries were exhausted. Postings it
                already collected are kept — start a new run to continue.
            </div>

            <ol class="flex flex-col gap-2" aria-label="Pipeline stages">
                <li
                    v-for="(stage, index) in STAGES"
                    :key="stage.key"
                    class="flex items-center gap-3 rounded-xl border border-border bg-card p-3 text-sm"
                    :aria-current="
                        stageState(index) === 'current' ? 'step' : undefined
                    "
                >
                    <span
                        class="size-2.5 rounded-full"
                        :class="STAGE_DOT[stageState(index)]"
                        aria-hidden="true"
                    />
                    {{ stage.label }}
                    <span class="sr-only">— {{ stageState(index) }}</span>
                </li>
            </ol>

            <dl
                class="grid grid-cols-2 gap-3 text-center md:grid-cols-4"
                aria-label="Run funnel"
            >
                <div class="rounded-lg bg-muted p-3">
                    <dt class="text-xs text-muted-foreground">Candidates</dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ run.candidates_count }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted p-3">
                    <dt class="text-xs text-muted-foreground">Gate passed</dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ run.gate_passed_count }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted p-3">
                    <dt class="text-xs text-muted-foreground">Extracted</dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ run.extracted_count }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted p-3">
                    <dt class="text-xs text-muted-foreground">Spend</dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ formatMicros(run.spend_micros) }}
                    </dd>
                </div>
            </dl>

            <PermissionGuard permission="VIEW_STUDIO_POSTINGS">
                <Button
                    v-if="run.status === 'finished'"
                    variant="outline"
                    @click="openReport"
                >
                    Open insight report
                </Button>
            </PermissionGuard>
        </template>
    </div>
</template>
