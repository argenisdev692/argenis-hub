<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    clientStatusLabel,
    clientStatusVariant,
    formatDateTime,
} from '../helpers/clientPresentation';
import type { Client } from '../types';

/**
 * Read-only detail for one client, opened from the row's "View" action.
 * Everything shown here is already on the list row, so there is no second
 * fetch — the dialog just renders `client` when it is non-null.
 */
const { client = null } = defineProps<{ client?: Client | null }>();

const open = defineModel<boolean>('open', { default: false });

type DetailRow = { label: string; value: string };
type LinkRow = { label: string; href: string };

const rows = computed<DetailRow[]>(() => {
    const record = client;

    if (!record) {
        return [];
    }

    return [
        { label: 'Email', value: record.email ?? '—' },
        { label: 'Phone', value: record.phone },
        { label: 'Country', value: record.country ?? '—' },
        { label: 'ISO code', value: record.country_code ?? '—' },
        { label: 'Address', value: record.address ?? '—' },
        { label: 'Tax ID', value: record.tax_id ?? '—' },
        { label: 'NIF', value: record.nif ?? '—' },
        { label: 'Created', value: formatDateTime(record.created_at) ?? '—' },
        {
            label: 'Last updated',
            value: formatDateTime(record.updated_at) ?? '—',
        },
    ];
});

const links = computed<LinkRow[]>(() => {
    const record = client;

    if (!record) {
        return [];
    }

    return (
        [
            { label: 'Website', href: record.website },
            { label: 'LinkedIn', href: record.linkedin_link },
            { label: 'Facebook', href: record.facebook_link },
            { label: 'Instagram', href: record.instagram_link },
            { label: 'X / Twitter', href: record.twitter_link },
        ] as { label: string; href: string | null }[]
    ).filter((row): row is LinkRow => Boolean(row.href));
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ client?.client_name ?? 'Client' }}</DialogTitle>
                <DialogDescription>
                    CRM record. Edits are audit-logged.
                </DialogDescription>
            </DialogHeader>

            <div v-if="client" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge :variant="clientStatusVariant(client.status)">
                        {{ clientStatusLabel(client.status) }}
                    </Badge>
                    <Badge v-if="client.deleted_at" variant="destructive">
                        Deleted
                    </Badge>
                </div>

                <dl class="grid gap-3 sm:grid-cols-[9rem_1fr]">
                    <template v-for="row in rows" :key="row.label">
                        <dt class="text-sm text-muted-foreground">
                            {{ row.label }}
                        </dt>
                        <dd class="text-sm break-words">{{ row.value }}</dd>
                    </template>
                </dl>

                <div v-if="links.length" class="flex flex-col gap-1">
                    <p class="text-sm text-muted-foreground">Links</p>
                    <ul class="flex flex-col gap-1">
                        <li
                            v-for="link in links"
                            :key="link.label"
                            class="text-sm"
                        >
                            <span class="text-muted-foreground"
                                >{{ link.label }}:</span
                            >
                            <a
                                :href="link.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="ml-1 break-all underline underline-offset-2"
                            >
                                {{ link.href }}
                            </a>
                        </li>
                    </ul>
                </div>

                <div v-if="client.notes" class="flex flex-col gap-1">
                    <p class="text-sm text-muted-foreground">Notes</p>
                    <p
                        class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm break-words whitespace-pre-wrap"
                    >
                        {{ client.notes }}
                    </p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
