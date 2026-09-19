<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useApplications } from '@/modules/cv-studio/composables/useApplications';
import { formatDate } from '@/modules/cv-studio/helpers/studioPresentation';
import type { StudioApplication } from '@/modules/cv-studio/types';
import { index as postingsIndex } from '@/routes/cv-studio/postings';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'CV Studio', href: postingsIndex() },
            { title: 'Applications' },
        ],
    },
});

const { applications, total, truncated } = useApplications();

type Column = {
    key: string;
    label: string;
    matches: (application: StudioApplication) => boolean;
};

const AWAITING_OUTCOMES = ['unknown', 'pending', 'no_reply'];

/**
 * Every application lands in exactly one column. `rejected` / `withdrawn`
 * get their own "Closed" column — without it a rejection made the card
 * vanish from the board.
 */
const COLUMNS: readonly Column[] = [
    {
        key: 'saved',
        label: 'Saved',
        matches: (application) =>
            ['saved', 'new'].includes(application.status),
    },
    {
        key: 'applied',
        label: 'Applied',
        matches: (application) =>
            application.status === 'applied' &&
            AWAITING_OUTCOMES.includes(application.outcome),
    },
    {
        key: 'screening',
        label: 'Screening',
        matches: (application) => application.outcome === 'screening',
    },
    {
        key: 'interview',
        label: 'Interview',
        matches: (application) => application.outcome === 'interview',
    },
    {
        key: 'offer',
        label: 'Offer',
        matches: (application) => application.outcome === 'offer',
    },
    {
        key: 'closed',
        label: 'Closed',
        matches: (application) =>
            ['rejected', 'withdrawn'].includes(application.outcome),
    },
];

function columnItems(column: Column): StudioApplication[] {
    return applications.value.filter(column.matches);
}

function outcomeLabel(application: StudioApplication): string {
    if (!AWAITING_OUTCOMES.includes(application.outcome)) {
        return application.outcome;
    }

    const ageDays = application.applied_at
        ? Math.floor(
              (Date.now() - new Date(application.applied_at).getTime()) /
                  86_400_000,
          )
        : 0;

    return ageDays >= 21 ? 'aged silence (counts as non-reply)' : 'awaiting reply';
}
</script>

<template>
    <Head title="CV Studio · Applications" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Applications</h1>
            <p class="text-sm text-muted-foreground">
                What you did with each posting, and what the employer did
                back — tracked separately. Aged silence counts as non-reply
                for analysis only; the stored value is never overwritten.
            </p>
        </header>

        <p
            v-if="truncated"
            role="status"
            class="rounded-lg border border-border bg-muted p-3 text-sm text-muted-foreground"
        >
            Showing the {{ applications.length }} most recently updated of
            {{ total }} applications.
        </p>

        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
            <section
                v-for="column in COLUMNS"
                :key="column.key"
                class="flex flex-col gap-2 rounded-xl border border-border bg-card p-3"
                :aria-label="`${column.label} column`"
            >
                <h2 class="text-sm font-semibold">
                    {{ column.label }}
                    <span class="font-normal text-muted-foreground tabular-nums">
                        ({{ columnItems(column).length }})
                    </span>
                </h2>
                <article
                    v-for="application in columnItems(column)"
                    :key="application.uuid"
                    class="rounded-lg bg-muted p-2 text-sm"
                >
                    <p class="font-medium">
                        {{ application.posting?.title ?? 'Unlinked posting' }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ application.posting?.employer_name ?? '' }} ·
                        {{ outcomeLabel(application) }} ·
                        {{ formatDate(application.applied_at) }}
                    </p>
                </article>
                <p
                    v-if="columnItems(column).length === 0"
                    class="text-xs text-muted-foreground"
                >
                    Empty.
                </p>
            </section>
        </div>
    </div>
</template>
