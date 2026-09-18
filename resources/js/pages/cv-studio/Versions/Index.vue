<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useQuery } from '@pinia/colada';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { formatDate } from '@/modules/cv-studio/helpers/studioPresentation';
import { index as postingsIndex } from '@/routes/cv-studio/postings';
import { index } from '@/routes/cv-studio/versions';
import { exportMethod } from '@/routes/cv-studio/versions';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'CV Studio', href: postingsIndex() },
            { title: 'Versions' },
        ],
    },
});

type CvVersion = {
    uuid: string;
    purpose: string;
    language: string;
    posting_id: number | null;
    created_at: string | null;
};

type VersionPage = {
    data: CvVersion[];
    total: number;
};

const { data } = useQuery<VersionPage>({
    key: () => ['studio-versions'],
    query: () => httpJson<VersionPage>(toUrl(index())),
    staleTime: 1000 * 60 * 2,
    gcTime: 1000 * 60 * 5,
});

const versions = computed(() => data.value?.data ?? []);

function downloadUrl(version: CvVersion, format: 'docx' | 'pdf'): string {
    return `${exportMethod(version.uuid).url}?format=${format}`;
}
</script>

<template>
    <Head title="CV Studio · Versions" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">CV versions</h1>
            <p class="text-sm text-muted-foreground">
                Every rewrite and tailored variant, with its source. The
                source CV is never overwritten — each version links back to
                the posting it was produced for.
            </p>
        </header>

        <ul class="flex flex-col gap-2">
            <li
                v-for="version in versions"
                :key="version.uuid"
                class="flex items-center justify-between gap-3 rounded-xl border border-border bg-card p-3"
            >
                <div class="min-w-0">
                    <p class="font-medium">
                        {{ version.purpose }} · {{ version.language }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDate(version.created_at) }}
                    </p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <a
                            :href="downloadUrl(version, 'docx')"
                            download
                        >
                            DOCX
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="downloadUrl(version, 'pdf')" download>
                            PDF
                        </a>
                    </Button>
                </div>
            </li>
        </ul>

        <p
            v-if="versions.length === 0"
            class="text-sm text-muted-foreground"
        >
            No versions yet — rewrite a CV or tailor one to a posting first.
        </p>
    </div>
</template>
