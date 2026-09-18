<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import FitScoreBadge from '@/modules/cv-studio/components/FitScoreBadge.vue';
import { usePostings } from '@/modules/cv-studio/composables/usePostings';
import {
    useBudgets,
    useOwnRates,
} from '@/modules/cv-studio/composables/useStudioCatalog';
import { formatMicros } from '@/modules/cv-studio/helpers/studioPresentation';
import { show } from '@/routes/cv-studio/postings';
import { index as postingsIndex } from '@/routes/cv-studio/postings';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'CV Studio · Dashboard' }],
    },
});

const { postings } = usePostings();
const { budgets } = useBudgets();
const { rates } = useOwnRates();

const scored = computed(() =>
    postings.value.filter((posting) => posting.total_score !== null),
);

const best = computed(() =>
    [...scored.value]
        .sort((a, b) => (b.total_score ?? 0) - (a.total_score ?? 0))
        .slice(0, 5),
);

const totalSpend = computed(() =>
    budgets.value.reduce((sum, budget) => sum + budget.spent_micros, 0),
);

const replyRate = computed(() => {
    const trials = rates.value.reduce(
        (sum, row) => sum + row.applications,
        0,
    );
    const positives = rates.value.reduce(
        (sum, row) => sum + row.positives,
        0,
    );

    if (trials === 0) {
        return null;
    }

    return { trials, positives, rate: positives / trials };
});
</script>

<template>
    <Head title="CV Studio · Dashboard" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">CV Studio</h1>
            <p class="text-sm text-muted-foreground">
                Profiles, last runs, spend and pending outcomes at a glance.
            </p>
        </header>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Postings tracked</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ postings.length }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Scored</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ scored.length }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Provider spend</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ formatMicros(totalSpend) }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
                <p class="text-xs text-muted-foreground">Reply rate</p>
                <p class="text-2xl font-semibold tabular-nums">
                    <template v-if="replyRate">
                        {{ (replyRate.rate * 100).toFixed(1) }}%
                        <span class="text-sm font-normal">
                            ({{ replyRate.positives }}/{{ replyRate.trials }})
                        </span>
                    </template>
                    <template v-else>—</template>
                </p>
            </div>
        </div>

        <section
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Next best applications"
        >
            <h2 class="mb-2 text-sm font-semibold">Next best applications</h2>
            <ul v-if="best.length > 0" class="flex flex-col gap-2">
                <li
                    v-for="posting in best"
                    :key="posting.uuid"
                    class="flex items-center justify-between gap-2 text-sm"
                >
                    <a
                        :href="show(posting.uuid).url"
                        class="truncate font-medium hover:underline"
                    >
                        {{ posting.title }}
                    </a>
                    <FitScoreBadge
                        :score="posting.total_score"
                        :band="posting.band"
                    />
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                Nothing scored yet — start a run from the
                <a :href="postingsIndex().url" class="underline">postings</a>
                page.
            </p>
        </section>
    </div>
</template>
