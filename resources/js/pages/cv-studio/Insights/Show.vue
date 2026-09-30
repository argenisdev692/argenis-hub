<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useInsightReport } from '@/modules/cv-studio/composables/useRuns';
import { useOwnRates } from '@/modules/cv-studio/composables/useStudioCatalog';
import { index as postingsIndex } from '@/routes/cv-studio/postings';

const { uuid } = defineProps<{ uuid: string }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'CV Studio', href: postingsIndex() },
            { title: 'Insights' },
        ],
    },
});

const { report: reportData } = useInsightReport(uuid, true);
const { rates } = useOwnRates();

const requirements = computed(
    () => reportData.value?.requirements.requirements ?? [],
);
const eligibility = computed(
    () => reportData.value?.requirements.eligibility ?? {},
);
const reachTable = computed(() => reportData.value?.reach_table ?? []);
const recommendations = computed(() => reportData.value?.recommendations ?? []);
const correlation = computed(
    () =>
        reportData.value?.outcome_correlation ?? {
            suppressed: true,
            rows: [],
        },
);
const jdsAnalyzed = computed(() => reportData.value?.jds_analyzed ?? 0);
const sampleNote = computed(() => reportData.value?.sample_note ?? null);

const eligibilityEntries = computed(() =>
    Object.entries(eligibility.value)
        .map(([requirement, count]) => ({ requirement, count }))
        .sort(
            (a, b) =>
                b.count - a.count || a.requirement.localeCompare(b.requirement),
        ),
);
</script>

<template>
    <Head title="CV Studio · Insights" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Market insights
            </h1>
            <p class="text-sm text-muted-foreground">
                Requirement frequency across every posting read — including
                capped and below-threshold ones. Directional, never causal.
            </p>
            <p class="text-sm text-muted-foreground">
                <span class="font-medium text-foreground tabular-nums">{{
                    jdsAnalyzed
                }}</span>
                {{ jdsAnalyzed === 1 ? 'job' : 'jobs' }} analysed
                <span v-if="sampleNote" class="text-amber-500">
                    · {{ sampleNote }}
                </span>
            </p>
        </header>

        <section
            v-if="recommendations.length > 0"
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Recommendations"
        >
            <h2 class="mb-2 text-sm font-semibold">What to lead with</h2>
            <ul class="flex list-disc flex-col gap-1 pl-5 text-sm">
                <li v-for="line in recommendations" :key="line">
                    {{ line }}
                </li>
            </ul>
            <p class="mt-2 text-xs text-muted-foreground">
                Built only from requirements your CV already evidences — this
                report never suggests adding a skill you do not have.
            </p>
        </section>

        <section
            v-if="eligibilityEntries.length > 0"
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Eligibility and availability"
        >
            <h2 class="mb-2 text-sm font-semibold">
                Eligibility &amp; availability
            </h2>
            <p class="mb-2 text-xs text-muted-foreground">
                Counted apart from skills: these are the clauses that decide
                whether you can take the job at all, not what it is built with.
            </p>
            <ul class="flex flex-col gap-1 text-sm">
                <li
                    v-for="row in eligibilityEntries"
                    :key="row.requirement"
                    class="flex items-center justify-between gap-2"
                >
                    <span>{{ row.requirement }}</span>
                    <span class="text-muted-foreground tabular-nums">
                        {{ row.count }} of {{ jdsAnalyzed }}
                    </span>
                </li>
            </ul>
        </section>

        <section
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Requirement frequency"
        >
            <h2 class="mb-2 text-sm font-semibold">Requirement frequency</h2>
            <ul
                v-if="requirements.length > 0"
                class="flex flex-col gap-1 text-sm"
            >
                <li
                    v-for="row in requirements"
                    :key="row.name"
                    class="flex items-center justify-between gap-2"
                >
                    <span>
                        {{ row.name }}
                        <span v-if="row.covered" class="text-xs text-success">
                            · covered
                        </span>
                    </span>
                    <span class="text-muted-foreground tabular-nums">
                        {{ row.count }} of {{ jdsAnalyzed }}
                        <template v-if="row.percentage !== null">
                            ({{ row.percentage }}%)
                        </template>
                    </span>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No postings read in this run — the report is still produced.
            </p>
        </section>

        <section
            v-if="reachTable.length > 0"
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Reach table"
        >
            <h2 class="mb-2 text-sm font-semibold">
                Reach table — postings each cap costs you
            </h2>
            <ul class="flex flex-col gap-1 text-sm">
                <li
                    v-for="row in reachTable"
                    :key="row.cap"
                    class="flex items-center justify-between gap-2"
                >
                    <span class="font-mono text-xs">{{ row.cap }}</span>
                    <span class="text-muted-foreground tabular-nums">
                        {{ row.unlocked_count }} of {{ row.capped_count }} would
                        clear the bar uncapped
                    </span>
                </li>
            </ul>
        </section>

        <section
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Outcome correlation"
        >
            <h2 class="mb-2 text-sm font-semibold">Answered vs silent</h2>
            <p
                v-if="correlation.suppressed"
                class="text-sm text-muted-foreground"
            >
                Suppressed — fewer than 8 usable outcomes. The sample cannot
                support a comparison yet.
            </p>
            <ul v-else class="flex flex-col gap-1 text-sm">
                <li
                    v-for="row in correlation.rows"
                    :key="row.requirement"
                    class="flex items-center justify-between gap-2"
                >
                    <span>{{ row.requirement }}</span>
                    <span class="text-muted-foreground tabular-nums">
                        answered {{ row.answered_rate ?? '—' }} · silent
                        {{ row.silent_rate ?? '—' }}
                    </span>
                </li>
            </ul>
        </section>

        <section
            v-if="rates.length > 0"
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Own reply rates"
        >
            <h2 class="mb-2 text-sm font-semibold">
                My reply rates by channel
            </h2>
            <ul class="flex flex-col gap-1 text-sm">
                <li
                    v-for="row in rates"
                    :key="row.bucket"
                    class="flex items-center justify-between gap-2"
                >
                    <span>{{ row.bucket }}</span>
                    <span class="text-muted-foreground tabular-nums">
                        {{ row.positives }}/{{ row.applications }}
                        <template v-if="row.rate !== null">
                            ({{ (row.rate * 100).toFixed(1) }}% · 90% CI
                            {{ (row.lower * 100).toFixed(1) }}–{{
                                (row.upper * 100).toFixed(1)
                            }}%)
                        </template>
                        <template v-if="!row.gate_passed">
                            · below evidence gate, not applied
                        </template>
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
