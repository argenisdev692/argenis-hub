<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { ConfirmModal, Paginator } from '@/common/table';
import { Button } from '@/components/ui/button';
import {
    useCvVersionExport,
    useCvVersions,
    usePromoteVersion,
} from '@/modules/cv-studio/composables/useCvVersions';
import type { CvExportFormat } from '@/modules/cv-studio/composables/useCvVersions';
import { formatDate } from '@/modules/cv-studio/helpers/studioPresentation';
import type { StudioCvVersion } from '@/modules/cv-studio/types';
import { index as postingsIndex } from '@/routes/cv-studio/postings';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'CV Studio', href: postingsIndex() },
            { title: 'Versions' },
        ],
    },
});

const { versions, meta, page, perPage, isLoading, isPending } = useCvVersions();
const { download } = useCvVersionExport();
const { promote } = usePromoteVersion();

const pendingPromote = ref<StudioCvVersion | null>(null);
const confirmPromoteOpen = ref(false);

function askPromote(version: StudioCvVersion): void {
    pendingPromote.value = version;
    confirmPromoteOpen.value = true;
}

function confirmPromote(): void {
    if (pendingPromote.value) {
        promote.mutate({ uuid: pendingPromote.value.uuid });
    }

    confirmPromoteOpen.value = false;
}

function isDownloading(
    version: StudioCvVersion,
    format: CvExportFormat,
): boolean {
    return (
        download.isLoading.value &&
        download.variables.value?.uuid === version.uuid &&
        download.variables.value?.format === format
    );
}
</script>

<template>
    <Head title="CV Studio · Versions" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">CV versions</h1>
            <p class="text-sm text-muted-foreground">
                Every rewrite and tailored variant, with its source. The source
                CV is never overwritten — each version links back to the posting
                it was produced for.
            </p>
        </header>

        <p v-if="isPending" class="text-sm text-muted-foreground">
            Loading versions…
        </p>

        <template v-else>
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
                        <Button
                            v-for="format in ['docx', 'pdf'] as const"
                            :key="format"
                            variant="outline"
                            size="sm"
                            :disabled="download.isLoading.value"
                            :aria-busy="isDownloading(version, format)"
                            @click="
                                download.mutate({ uuid: version.uuid, format })
                            "
                        >
                            {{
                                isDownloading(version, format)
                                    ? 'Preparing…'
                                    : format.toUpperCase()
                            }}
                        </Button>
                        <PermissionGuard permission="UPDATE_STUDIO_POSTINGS">
                            <Button
                                variant="secondary"
                                size="sm"
                                :disabled="promote.isLoading.value"
                                @click="askPromote(version)"
                            >
                                Set as primary
                            </Button>
                        </PermissionGuard>
                    </div>
                </li>
            </ul>

            <p
                v-if="versions.length === 0"
                class="text-sm text-muted-foreground"
            >
                No versions yet — rewrite a CV or tailor one to a posting first.
            </p>

            <Paginator
                v-else
                v-model:page="page"
                v-model:per-page="perPage"
                :meta="meta"
                :disabled="isLoading"
                label="versions"
                class="rounded-xl border border-border bg-card"
            />
        </template>
    </div>

    <ConfirmModal
        v-model:open="confirmPromoteOpen"
        title="Set this version as the primary CV?"
        description="A new CV row is created from this version and becomes primary — the current primary is kept as history, never overwritten. The new CV is re-parsed immediately so search and matching use it."
        confirm-label="Set as primary"
        @confirm="confirmPromote"
    >
        <p v-if="pendingPromote" class="text-sm text-muted-foreground">
            {{ pendingPromote.purpose }} · {{ pendingPromote.language }} ·
            {{ formatDate(pendingPromote.created_at) }}
        </p>
    </ConfirmModal>
</template>
