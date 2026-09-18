<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useQuery } from '@pinia/colada';
import { computed } from 'vue';
import { httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { formatDate } from '@/modules/cv-studio/helpers/studioPresentation';
import type { StudioApplication } from '@/modules/cv-studio/types';
import { index } from '@/routes/cv-studio/applications';
import { index as postingsIndex } from '@/routes/cv-studio/postings';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'CV Studio', href: postingsIndex() },
            { title: 'Applications' },
        ],
    },
});

type ApplicationPage = {
    data: StudioApplication[];
    total: number;
};

const { data } = useQuery<ApplicationPage>({
    key: () => ['studio-applications'],
    query: () => httpJson<ApplicationPage>(toUrl(index())),
    staleTime: 1000 * 60 * 2,
    gcTime: 1000 * 60 * 5,
});

const applications = computed(() => data.value?.data ?? []);

const COLUMNS = ['saved', 'applied', 'screening', 'interview', 'offer'] as const;

function columnItems(status: string): StudioApplication[] {
    if (status === 'saved') {
        return applications.value.filter((application) =>
            ['saved', 'new'].includes(application.status),
        );
    }

    if (status === 'applied') {
        return applications.value.filter(
            (application) =>
                application.status === 'applied' &&
                ['unknown', 'pending', 'no_reply'].includes(
                    application.outcome,
                ),
        );
    }

    return applications.value.filter(
        (application) => application.outcome === status,
    );
}

function outcomeLabel(application: StudioApplication): string {
    if (
        ['screening', 'interview', 'offer'].includes(application.outcome) ||
        application.outcome === 'rejected'
    ) {
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

        <div class="grid gap-3 md:grid-cols-5">
            <section
                v-for="column in COLUMNS"
                :key="column"
                class="flex flex-col gap-2 rounded-xl border border-border bg-card p-3"
                :aria-label="`${column} column`"
            >
                <h2 class="text-sm font-semibold capitalize">{{ column }}</h2>
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
