<script setup lang="ts">
import { DownloadIcon, Loader2Icon } from '@lucide/vue';
import { computed } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { useBackup } from '@/modules/backups/composables/useBackup';
import { formatBackupTimestamp } from '@/modules/backups/helpers/formatBackupTimestamp';
import { download } from '@/routes/backups/admin';
import BackupStatusBadge from './BackupStatusBadge.vue';

/**
 * Read-only detail for one archive, opened from the row's "View" action.
 *
 * `uuid` is `null` while the dialog is closed; `useBackup` keeps its query
 * disabled until a row is picked, so this stays inert on the index page until
 * it is actually opened.
 */
const { uuid = null } = defineProps<{ uuid?: string | null }>();

const open = defineModel<boolean>('open', { default: false });

const { backup, isPending, error } = useBackup(() => uuid);

const errorMessage = computed(() =>
    error.value instanceof HttpError
        ? error.value.message
        : 'Could not load this backup.',
);

type DetailRow = { label: string; value: string; mono?: boolean };

const rows = computed<DetailRow[]>(() => {
    const record = backup.value;

    if (!record) {
        return [];
    }

    return [
        { label: 'File name', value: record.filename, mono: true },
        { label: 'Disk', value: record.disk },
        { label: 'Path', value: record.path ?? '—', mono: true },
        { label: 'Size', value: record.human_size },
        { label: 'Connection', value: record.connection ?? '—' },
        { label: 'Started', value: formatBackupTimestamp(record.started_at) },
        { label: 'Finished', value: formatBackupTimestamp(record.finished_at) },
        { label: 'Created', value: formatBackupTimestamp(record.created_at) },
    ];
});

const canDownload = computed(
    () => backup.value?.status === 'completed' && Boolean(backup.value?.path),
);

function downloadArchive(): void {
    if (!uuid) {
        return;
    }

    window.location.assign(toUrl(download(uuid)));
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Backup archive</DialogTitle>
                <DialogDescription>
                    A database snapshot produced by the scheduler or an
                    on-demand run.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="isPending"
                class="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground"
            >
                <Loader2Icon class="size-4 animate-spin" aria-hidden="true" />
                Loading…
            </div>

            <p
                v-else-if="error"
                class="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive"
                role="alert"
            >
                {{ errorMessage }}
            </p>

            <div v-else-if="backup" class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <BackupStatusBadge :status="backup.status" />
                </div>

                <p
                    v-if="backup.error"
                    class="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm break-words text-destructive"
                >
                    {{ backup.error }}
                </p>

                <dl class="grid gap-3 sm:grid-cols-[10rem_1fr]">
                    <template v-for="row in rows" :key="row.label">
                        <dt class="text-sm text-muted-foreground">
                            {{ row.label }}
                        </dt>
                        <dd
                            class="text-sm break-words"
                            :class="row.mono && 'font-mono text-xs break-all'"
                        >
                            {{ row.value }}
                        </dd>
                    </template>
                </dl>
            </div>

            <DialogFooter>
                <PermissionGuard permission="DOWNLOAD_BACKUPS">
                    <Button
                        variant="outline"
                        :disabled="!canDownload"
                        @click="downloadArchive"
                    >
                        <DownloadIcon class="size-4" aria-hidden="true" />
                        Download
                    </Button>
                </PermissionGuard>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
