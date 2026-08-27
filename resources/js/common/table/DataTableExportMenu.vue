<script setup lang="ts">
import {
    DownloadIcon,
    FileSpreadsheetIcon,
    FileTextIcon,
    Loader2Icon,
} from '@lucide/vue';
import type { Component } from 'vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ExportFormat } from './types';

/**
 * "Export ▾" for the current, filtered list.
 *
 * Each item is a plain top-level GET to `endpoint?format=…&<filters>` — the
 * server answers with a streamed `Content-Disposition: attachment`, so the
 * browser owns the save dialog and the page never unloads. An XHR would buffer
 * the whole file in memory and still need a second hop to hand it over.
 *
 * The same `params` the list query uses go on the query string, so the export
 * always matches exactly what the user is looking at.
 */

const {
    endpoint,
    params = {},
    formats = ['xlsx', 'pdf'],
    label = 'Export',
    disabled = false,
} = defineProps<{
    /** Absolute path of the export route, e.g. `/data/admin/services/export`. */
    endpoint: string;
    /** Current filter state — serialised onto the query string beside `format`. */
    params?: Record<string, string | number | boolean | null | undefined>;
    formats?: readonly ExportFormat[];
    label?: string;
    disabled?: boolean;
}>();

const FORMAT_META: Record<ExportFormat, { label: string; icon: Component }> = {
    xlsx: { label: 'Excel (.xlsx)', icon: FileSpreadsheetIcon },
    csv: { label: 'CSV (.csv)', icon: FileSpreadsheetIcon },
    pdf: { label: 'PDF (.pdf)', icon: FileTextIcon },
};

/** The format whose download was last started — drives a brief spinner. */
const pending = ref<ExportFormat | null>(null);

function buildUrl(format: ExportFormat): string {
    const query = new URLSearchParams({ format });

    for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== null && value !== '') {
            query.set(key, String(value));
        }
    }

    return `${endpoint}?${query.toString()}`;
}

function download(format: ExportFormat): void {
    pending.value = format;
    window.location.assign(buildUrl(format));

    // The response is a streamed attachment, so no navigation/`load` event
    // follows to clear the indicator — fall back to a short timer.
    window.setTimeout(() => {
        if (pending.value === format) {
            pending.value = null;
        }
    }, 4000);
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" :disabled="disabled">
                <Loader2Icon
                    v-if="pending"
                    class="size-4 animate-spin"
                    aria-hidden="true"
                />
                <DownloadIcon v-else class="size-4" aria-hidden="true" />
                {{ label }}
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end">
            <DropdownMenuItem
                v-for="format in formats"
                :key="format"
                :disabled="pending !== null"
                @select="download(format)"
            >
                <component
                    :is="FORMAT_META[format].icon"
                    class="size-4"
                    aria-hidden="true"
                />
                {{ FORMAT_META[format].label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
