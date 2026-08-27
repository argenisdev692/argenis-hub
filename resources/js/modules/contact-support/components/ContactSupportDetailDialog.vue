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
    contactName,
    formatDateTime,
} from '../helpers/contactSupportPresentation';
import type { ContactSupport } from '../types';

/**
 * Read-only detail for one support request, opened from the row's "View"
 * action. Everything shown here is already on the list row, so there is no
 * second fetch — the dialog just renders `support` when it is non-null.
 */
const { support = null } = defineProps<{ support?: ContactSupport | null }>();

const open = defineModel<boolean>('open', { default: false });

type DetailRow = { label: string; value: string };

const rows = computed<DetailRow[]>(() => {
    const record = support;

    if (!record) {
        return [];
    }

    return [
        { label: 'Name', value: contactName(record) },
        { label: 'Email', value: record.email },
        { label: 'Phone', value: record.phone },
        { label: 'Subject', value: record.subject },
        {
            label: 'Received',
            value: formatDateTime(record.created_at) ?? '—',
        },
        {
            label: 'Last updated',
            value: formatDateTime(record.updated_at) ?? '—',
        },
    ];
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Support request</DialogTitle>
                <DialogDescription>
                    Captured from the public contact form.
                </DialogDescription>
            </DialogHeader>

            <div v-if="support" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge :variant="support.readed ? 'secondary' : 'default'">
                        {{ support.readed ? 'Read' : 'Unread' }}
                    </Badge>
                    <Badge v-if="support.is_spam" variant="destructive">
                        Spam · score {{ support.spam_score }}
                    </Badge>
                    <Badge v-if="support.sms_consent" variant="outline">
                        SMS consent
                    </Badge>
                    <Badge v-if="support.deleted_at" variant="destructive">
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

                <div class="flex flex-col gap-1">
                    <p class="text-sm text-muted-foreground">Message</p>
                    <p
                        class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm break-words whitespace-pre-wrap"
                    >
                        {{ support.message }}
                    </p>
                </div>

                <div
                    v-if="
                        support.spam_reasons && support.spam_reasons.length > 0
                    "
                    class="flex flex-col gap-1"
                >
                    <p class="text-sm text-muted-foreground">Spam reasons</p>
                    <ul class="flex flex-wrap gap-1.5">
                        <li
                            v-for="reason in support.spam_reasons"
                            :key="reason"
                        >
                            <Badge variant="outline">{{ reason }}</Badge>
                        </li>
                    </ul>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
