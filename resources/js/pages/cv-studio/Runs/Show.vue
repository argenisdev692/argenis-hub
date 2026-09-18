<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
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
            <ol class="flex flex-col gap-2" aria-label="Pipeline stages">
                <li
                    v-for="stage in STAGES"
                    :key="stage.key"
                    class="flex items-center gap-3 rounded-xl border border-border bg-card p-3 text-sm"
                >
                    <span
                        class="size-2.5 rounded-full"
                        :class="
                            run.status === stage.key ||
                            (stage.key === 'finished' &&
                                run.status === 'finished')
                                ? 'bg-emerald-500'
                                : 'bg-muted'
                        "
                        aria-hidden="true"
                    />
                    {{ stage.label }}
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
                    <dt class="text-xs text-muted-foreground">Scored</dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ run.scored_count }}
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
