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
    currencyLabel,
    formatDate,
    maskedIdentifier,
    paymentMethodLabel,
    paymentMethodVariant,
} from '../helpers/paymentAccountPresentation';
import type { PaymentAccount } from '../types';

/**
 * Read-only detail for one settlement rail.
 *
 * The account identifier is shown MASKED, never in full: this pane gets
 * screen-shared, and the operator only ever needs enough to tell two rails
 * apart. Editing reveals the value in the form, which is a deliberate action.
 */
const { account = null } = defineProps<{ account?: PaymentAccount | null }>();

const open = defineModel<boolean>('open', { default: false });

type DetailRow = { label: string; value: string };

const rows = computed<DetailRow[]>(() => {
    const record = account;

    if (!record) {
        return [];
    }

    return [
        { label: 'Currency', value: currencyLabel(record.currency) },
        { label: 'Beneficiary', value: record.beneficiary ?? '—' },
        { label: 'Bank', value: record.bank_name ?? '—' },
        { label: 'Account', value: maskedIdentifier(record) },
        { label: 'BIC / SWIFT', value: record.bic ?? '—' },
        { label: 'Routing number', value: record.routing_number ?? '—' },
        { label: 'Holder email', value: record.holder_email ?? '—' },
        { label: 'Holder phone', value: record.holder_phone ?? '—' },
        { label: 'Sort order', value: String(record.sort_order) },
        { label: 'Created', value: formatDate(record.created_at) ?? '—' },
    ];
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ account?.label ?? 'Payment account' }}
                </DialogTitle>
                <DialogDescription>
                    Settlement rail. Invoices copy these details at issue time,
                    so editing here never rewrites an invoice already sent.
                </DialogDescription>
            </DialogHeader>

            <div v-if="account" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge :variant="paymentMethodVariant(account.method)">
                        {{ paymentMethodLabel(account.method) }}
                    </Badge>
                    <Badge v-if="account.is_default" variant="secondary">
                        Default
                    </Badge>
                    <Badge v-if="!account.is_active" variant="outline">
                        Inactive
                    </Badge>
                    <Badge v-if="account.deleted_at" variant="destructive">
                        Deleted
                    </Badge>
                </div>

                <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-2">
                    <div v-for="row in rows" :key="row.label" class="text-sm">
                        <dt class="text-muted-foreground">{{ row.label }}</dt>
                        <dd class="font-medium">{{ row.value }}</dd>
                    </div>
                </dl>

                <div v-if="account.instructions" class="text-sm">
                    <p class="pb-1 text-muted-foreground">Instructions</p>
                    <p class="whitespace-pre-line">
                        {{ account.instructions }}
                    </p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
