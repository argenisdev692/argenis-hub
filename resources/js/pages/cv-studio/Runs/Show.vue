<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { Button } from '@/components/ui/button';
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

const { run, isLoading } = useRunStatus(uuid);

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

function openReport(): void {
    router.visit(report(uuid).url);
}
</script>

<template>
    <Head title="CV Studio · Run" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Discovery run
            </h1>
            <p class="font-mono text-xs text-muted-foreground">{{ uuid }}</p>
        </header>

        <div v-if="isLoading" class="text-sm text-muted-foreground">
            Loading run status…
        </div>

        <template v-else-if="run">
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
