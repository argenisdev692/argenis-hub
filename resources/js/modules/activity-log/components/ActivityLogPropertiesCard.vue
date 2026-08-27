<script setup lang="ts">
import { computed } from 'vue';
import EmptyState from '@/common/feedback/EmptyState.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

/**
 * Renders one of the audit trail's free-form JSON blobs (`properties` or
 * `attribute_changes`) as a read-only definition list.
 *
 * Values are treated as opaque: scalars print as-is, everything else is
 * pretty-printed JSON inside a `<pre>`. No `v-html`, no interpretation — the
 * blob holds whatever a logging model put in `logOnly([...])`, and the point
 * of the screen is to show exactly that.
 */
const { title, data } = defineProps<{
    title: string;
    data: Record<string, unknown> | null;
}>();

type Row = { key: string; scalar: string | null; json: string | null };

function isScalar(value: unknown): value is string | number | boolean {
    return (
        typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'boolean'
    );
}

const rows = computed<Row[]>(() =>
    Object.entries(data ?? {}).map(([key, value]) => {
        if (value === null) {
            return { key, scalar: '—', json: null };
        }

        if (isScalar(value)) {
            return { key, scalar: String(value), json: null };
        }

        return { key, scalar: null, json: JSON.stringify(value, null, 2) };
    }),
);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
        </CardHeader>
        <CardContent>
            <dl v-if="rows.length" class="grid gap-3 sm:grid-cols-[12rem_1fr]">
                <template v-for="row in rows" :key="row.key">
                    <dt
                        class="font-mono text-sm break-all text-muted-foreground"
                    >
                        {{ row.key }}
                    </dt>
                    <dd class="text-sm">
                        <span v-if="row.scalar !== null" class="break-words">
                            {{ row.scalar }}
                        </span>
                        <pre
                            v-else
                            class="overflow-x-auto rounded-md bg-muted/50 p-3 text-xs"
                        ><code>{{ row.json }}</code></pre>
                    </dd>
                </template>
            </dl>

            <EmptyState
                v-else
                title="No data recorded"
                :description="`This entry carries no ${title.toLowerCase()}.`"
            />
        </CardContent>
    </Card>
</template>
