<script setup lang="ts">
import { AppField, FormDialog, TextField } from '@/common/form';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ClientOption } from '@/modules/clients/composables/useClientOptions';
import { useProductForm } from '../composables/useProductForm';
import {
    billingUnitLabel,
    productStatusLabel,
    productTypeLabel,
} from '../helpers/productPresentation';
import {
    BILLING_UNIT_VALUES,
    isBillingUnit,
    isProductStatus,
    isProductType,
    PRODUCT_LEVEL_VALUES,
    PRODUCT_MODALITY_VALUES,
    PRODUCT_STATUS_VALUES,
    PRODUCT_TYPE_VALUES,
} from '../schemas/productFormSchema';
import type { Product } from '../types';

/**
 * One dialog for both create and edit.
 *
 * `product` is `null` in create mode; `useProductForm` reads it at submit time
 * to decide between `POST` and `PUT`, so this component only has to carry the
 * prop through, not branch on it itself.
 *
 * `clientOptions` arrives as a prop rather than being fetched here: the page
 * owns the query (Pages may import from modules, modules may not reach
 * sideways), and the same list also feeds the invoice form.
 */
const { product = null, clientOptions = () => [] } = defineProps<{
    product?: Product | null;
    clientOptions?: ClientOption[] | (() => ClientOption[]);
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useProductForm({
    open,
    product: () => product,
});

/** Normalises the prop so the template never branches on its shape. */
function options(): ClientOption[] {
    return typeof clientOptions === 'function' ? clientOptions() : clientOptions;
}

/** reka `Select` has no empty-string item, so "no client" gets its own sentinel. */
const NO_CLIENT = '__none__';
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="product ? 'Edit product' : 'New product'"
        description="A billable catalog entry — a live course or a recorded video course. Published products appear in the invoice line-item picker."
        submit-label="Save"
        content-class="sm:max-w-3xl"
    >
        <div class="grid gap-4">
            <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                <form.Field name="title" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Title"
                        required
                        placeholder="GitHub Copilot para Desarrolladores Web"
                        description="The slug is derived from this automatically."
                    />
                </form.Field>

                <form.Field name="type" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Type"
                        required
                        #default="{ control }"
                    >
                        <Select
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) =>
                                    isProductType(value) &&
                                    field.handleChange(value)
                            "
                        >
                            <SelectTrigger v-bind="control" class="w-full">
                                <SelectValue placeholder="Select a type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="type in PRODUCT_TYPE_VALUES"
                                    :key="type"
                                    :value="type"
                                >
                                    {{ productTypeLabel(type) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </AppField>
                </form.Field>
            </div>

            <form.Field name="description" #default="{ field }">
                <TextField
                    :field="field"
                    label="Description"
                    multiline
                    :rows="5"
                    description="Line breaks are preserved on the invoice PDF — use them for the session breakdown."
                    placeholder="Formación completa en 8 sesiones:"
                />
            </form.Field>

            <div class="grid gap-4 sm:grid-cols-3">
                <form.Field name="price" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Price"
                        required
                        inputmode="decimal"
                        placeholder="52.00"
                    />
                </form.Field>

                <form.Field name="currency" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Currency"
                        required
                        placeholder="EUR"
                    />
                </form.Field>

                <form.Field name="default_unit" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Priced per"
                        required
                        description="Pre-fills the invoice line's unit."
                        #default="{ control }"
                    >
                        <Select
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) =>
                                    isBillingUnit(value) &&
                                    field.handleChange(value)
                            "
                        >
                            <SelectTrigger v-bind="control" class="w-full">
                                <SelectValue placeholder="Select a unit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="unit in BILLING_UNIT_VALUES"
                                    :key="unit"
                                    :value="unit"
                                >
                                    {{ billingUnitLabel(unit) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </AppField>
                </form.Field>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <form.Field name="status" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Catalog status"
                        required
                        description="Only published products can be billed."
                        #default="{ control }"
                    >
                        <Select
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) =>
                                    isProductStatus(value) &&
                                    field.handleChange(value)
                            "
                        >
                            <SelectTrigger v-bind="control" class="w-full">
                                <SelectValue placeholder="Select a status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in PRODUCT_STATUS_VALUES"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ productStatusLabel(status) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </AppField>
                </form.Field>

                <form.Field name="level" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Level"
                        #default="{ control }"
                    >
                        <Select
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) => field.handleChange(String(value))
                            "
                        >
                            <SelectTrigger v-bind="control" class="w-full">
                                <SelectValue placeholder="Select a level" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="level in PRODUCT_LEVEL_VALUES"
                                    :key="level"
                                    :value="level"
                                    class="capitalize"
                                >
                                    {{ level }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </AppField>
                </form.Field>

                <form.Field name="language" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Language"
                        required
                        placeholder="es"
                    />
                </form.Field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <form.Field name="client_uuid" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Client"
                        description="Optional — set it for an in-company course commissioned by one client."
                        #default="{ control }"
                    >
                        <Select
                            :model-value="field.state.value || NO_CLIENT"
                            @update:model-value="
                                (value) =>
                                    field.handleChange(
                                        value === NO_CLIENT ? '' : String(value),
                                    )
                            "
                        >
                            <SelectTrigger v-bind="control" class="w-full">
                                <SelectValue placeholder="No client" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NO_CLIENT">
                                    No client
                                </SelectItem>
                                <SelectItem
                                    v-for="option in options()"
                                    :key="option.uuid"
                                    :value="option.uuid"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </AppField>
                </form.Field>

                <form.Field name="modality" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Modality"
                        #default="{ control }"
                    >
                        <Select
                            :model-value="field.state.value || NO_CLIENT"
                            @update:model-value="
                                (value) =>
                                    field.handleChange(
                                        value === NO_CLIENT ? '' : String(value),
                                    )
                            "
                        >
                            <SelectTrigger v-bind="control" class="w-full">
                                <SelectValue placeholder="Not set" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NO_CLIENT">
                                    Not set
                                </SelectItem>
                                <SelectItem
                                    v-for="modality in PRODUCT_MODALITY_VALUES"
                                    :key="modality"
                                    :value="modality"
                                    class="capitalize"
                                >
                                    {{ modality }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </AppField>
                </form.Field>
            </div>

            <div class="grid gap-4 sm:grid-cols-4">
                <form.Field name="start_date" #default="{ field }">
                    <TextField :field="field" label="Start date" type="date" />
                </form.Field>

                <form.Field name="end_date" #default="{ field }">
                    <TextField :field="field" label="End date" type="date" />
                </form.Field>

                <form.Field name="total_hours" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Total hours"
                        inputmode="decimal"
                        placeholder="25"
                    />
                </form.Field>

                <form.Field name="total_sessions" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Sessions"
                        inputmode="numeric"
                        placeholder="8"
                    />
                </form.Field>
            </div>

            <form.Field name="notes" #default="{ field }">
                <TextField
                    :field="field"
                    label="Internal notes"
                    multiline
                    :rows="3"
                    description="Never printed on an invoice."
                />
            </form.Field>
        </div>
    </FormDialog>
</template>
