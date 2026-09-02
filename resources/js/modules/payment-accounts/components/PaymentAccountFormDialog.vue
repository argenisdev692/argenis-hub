<script setup lang="ts">
import { computed } from 'vue';
import { AppField, FormDialog, TextField } from '@/common/form';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { usePaymentAccountForm } from '../composables/usePaymentAccountForm';
import { paymentMethodLabel } from '../helpers/paymentAccountPresentation';
import {
    isPaymentMethod,
    PAYMENT_METHOD_VALUES,
} from '../schemas/paymentAccountFormSchema';
import type { PaymentAccount } from '../types';

/**
 * One dialog for both create and edit.
 *
 * `account` is `null` in create mode; `usePaymentAccountForm` reads it at
 * submit time to decide between `POST` and `PUT`.
 */
const { account = null } = defineProps<{ account?: PaymentAccount | null }>();

const open = defineModel<boolean>('open', { default: false });

const form = usePaymentAccountForm({
    open,
    account: () => account,
});

/**
 * Bank fields only make sense for a wire. A Remitly or PayPal rail is
 * identified by a handle, so showing IBAN/BIC there is just noise the user has
 * to skip past. The schema still accepts either, so nothing breaks if a rail
 * genuinely carries both.
 */
const methodField = form.useStore((state) => state.values.method);
const isBankAccount = computed(() => methodField.value === 'BANK_TRANSFER');
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="account ? 'Edit payment account' : 'New payment account'"
        description="A settlement rail invoices copy at issue time. Method and currency are independent — Remitly/USD and bank transfer/USD are both valid."
        submit-label="Save"
        content-class="sm:max-w-2xl"
    >
        <div class="grid gap-4">
            <div class="grid gap-4 sm:grid-cols-[1fr_10rem]">
                <form.Field name="label" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Label"
                        required
                        placeholder="Montepio EUR"
                        description="How you recognise this rail in the invoice picker."
                    />
                </form.Field>

                <form.Field name="method" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Method"
                        required
                        #default="{ control }"
                    >
                        <Select
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) =>
                                    isPaymentMethod(value) &&
                                    field.handleChange(value)
                            "
                        >
                            <SelectTrigger v-bind="control" class="w-full">
                                <SelectValue placeholder="Select a method" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="method in PAYMENT_METHOD_VALUES"
                                    :key="method"
                                    :value="method"
                                >
                                    {{ paymentMethodLabel(method) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </AppField>
                </form.Field>
            </div>

            <div class="grid gap-4 sm:grid-cols-[10rem_1fr]">
                <form.Field name="currency" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Currency"
                        placeholder="EUR"
                        description="Blank = any currency."
                    />
                </form.Field>

                <form.Field name="beneficiary" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Beneficiary"
                        placeholder="Argenis Jose Carrillo Gonzalez"
                    />
                </form.Field>
            </div>

            <template v-if="isBankAccount">
                <div class="grid gap-4 sm:grid-cols-[1fr_10rem]">
                    <form.Field name="iban" #default="{ field }">
                        <TextField
                            :field="field"
                            label="IBAN"
                            placeholder="PT50 0036 0011 9910 0063 053 49"
                        />
                    </form.Field>

                    <form.Field name="bic" #default="{ field }">
                        <TextField
                            :field="field"
                            label="BIC / SWIFT"
                            placeholder="MPIOPTPL"
                        />
                    </form.Field>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <form.Field name="bank_name" #default="{ field }">
                        <TextField
                            :field="field"
                            label="Bank"
                            placeholder="Montepio"
                        />
                    </form.Field>

                    <form.Field name="account_number" #default="{ field }">
                        <TextField
                            :field="field"
                            label="Account number"
                            description="For rails without an IBAN (US wires)."
                        />
                    </form.Field>

                    <form.Field name="routing_number" #default="{ field }">
                        <TextField :field="field" label="Routing number" />
                    </form.Field>
                </div>
            </template>

            <div class="grid gap-4 sm:grid-cols-2">
                <form.Field name="holder_email" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Holder email"
                        type="email"
                        inputmode="email"
                        placeholder="you@example.com"
                        :description="
                            isBankAccount
                                ? undefined
                                : 'How the sender identifies you on this rail.'
                        "
                    />
                </form.Field>

                <form.Field name="holder_phone" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Holder phone"
                        type="tel"
                        inputmode="tel"
                        placeholder="+351963490414"
                    />
                </form.Field>
            </div>

            <form.Field name="instructions" #default="{ field }">
                <TextField
                    :field="field"
                    label="Instructions"
                    multiline
                    :rows="3"
                    description="Printed under the payment block on unpaid invoices."
                />
            </form.Field>

            <div class="grid gap-4 sm:grid-cols-2">
                <form.Field name="is_default" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Default for this currency"
                        description="Pre-selected on new invoices in this currency. One default per currency."
                        orientation="horizontal"
                        #default="{ control }"
                    >
                        <Switch
                            v-bind="control"
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) => field.handleChange(value)
                            "
                        />
                    </AppField>
                </form.Field>

                <form.Field name="is_active" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Active"
                        description="Inactive rails stay on old invoices but leave the picker."
                        orientation="horizontal"
                        #default="{ control }"
                    >
                        <Switch
                            v-bind="control"
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) => field.handleChange(value)
                            "
                        />
                    </AppField>
                </form.Field>
            </div>
        </div>
    </FormDialog>
</template>
