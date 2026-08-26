<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, Loader2Icon } from '@lucide/vue';
import { m } from 'motion-v';
import { ref, watch } from 'vue';
import type { ResolvedAddress } from '@/common/form';
import { AddressInput, TextField } from '@/common/form';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    MOTION_STAGGER_TIGHT,
    REVEAL_ITEM,
    staggerContainer,
} from '@/lib/motion';
import { useCompanyForm } from '@/modules/company/composables/useCompanyForm';
import type { CompanyFieldSpec } from '@/modules/company/helpers/companyFields';
import {
    ADDRESS_FIELDS,
    CONTACT_FIELDS,
    FISCAL_FIELDS,
    IDENTITY_FIELDS,
    SOCIAL_FIELDS,
    toControlProps,
} from '@/modules/company/helpers/companyFields';
import type { CompanyProfile } from '@/modules/company/types';
import { edit as editCompany, show as showCompany } from '@/routes/company';

/**
 * Every editable field on one page.
 *
 * The section dialogs on `Show.vue` cover the common case of correcting one
 * thing; this exists for the uncommon one — initial setup, or an audit pass
 * where someone works down the whole record. Same schema, same payload, same
 * route: the two surfaces differ only in how much they show at once.
 */
const { company } = defineProps<{ company: CompanyProfile }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Company', href: showCompany() },
            { title: 'Edit', href: editCompany() },
        ],
    },
});

const form = useCompanyForm({
    company: () => company,
    successMessage: 'Company details updated.',
});

const isSubmitting = form.useStore((state) => state.isSubmitting);
const canSubmit = form.useStore((state) => state.canSubmit);

type CompanySectionSpec = {
    title: string;
    description: string;
    fields: readonly CompanyFieldSpec[];
    /** Renders the Places lookup above the fields. Address only. */
    lookup?: boolean;
};

const SECTIONS: readonly CompanySectionSpec[] = [
    {
        title: 'Identity',
        description: 'How the company is named across the product.',
        fields: IDENTITY_FIELDS,
    },
    {
        title: 'Contact',
        description: 'Where customers reach you.',
        fields: CONTACT_FIELDS,
    },
    {
        title: 'Address',
        description: 'Used on invoices and the public site.',
        fields: ADDRESS_FIELDS,
        lookup: true,
    },
    {
        title: 'Fiscal & banking',
        description: 'Tax identifiers and payment details for invoices.',
        fields: FISCAL_FIELDS,
    },
    {
        title: 'Social profiles',
        description: 'Full profile URLs, including https://.',
        fields: SOCIAL_FIELDS,
    },
];

const sectionCascade = staggerContainer(MOTION_STAGGER_TIGHT);

const addressLookup = ref<ResolvedAddress | null>(null);
const addressLookupText = ref('');

/**
 * The Places selection fills the postal fields and, crucially, the coordinates.
 *
 * `latitude` and `longitude` have no inputs of their own — they ride along from
 * here, which is exactly why `UpdateCompanyData` bounds-checks them server-side
 * as well. Everything written here stays editable: Places often returns no
 * postal code for a street-level match, and sometimes returns one belonging to
 * the adjoining district, so the operator holding the utility bill gets the
 * final say.
 */
watch(addressLookup, (resolved) => {
    if (!resolved) {
        return;
    }

    const street = [resolved.street_number, resolved.route]
        .filter(Boolean)
        .join(' ');

    form.setFieldValue('address', street || resolved.formatted_address);
    form.setFieldValue('zip_code', resolved.postal_code ?? '');
    form.setFieldValue('city', resolved.locality ?? '');
    form.setFieldValue('state', resolved.administrative_area ?? '');
    form.setFieldValue('country', resolved.country ?? '');
    form.setFieldValue(
        'country_code',
        resolved.country_code?.toUpperCase() ?? '',
    );
    form.setFieldValue('latitude', resolved.latitude);
    form.setFieldValue('longitude', resolved.longitude);
});

async function onSubmit(event: Event): Promise<void> {
    event.preventDefault();
    await form.handleSubmit();
}
</script>

<template>
    <Head title="Edit company" />

    <h1 class="sr-only">Edit company settings</h1>

    <form class="flex flex-col gap-6" novalidate @submit="onSubmit">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Edit company"
                description="Every field on the company record"
            />

            <Button variant="ghost" size="sm" as-child>
                <Link :href="showCompany()">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                    Back
                </Link>
            </Button>
        </div>

        <m.div
            class="flex flex-col gap-6"
            :variants="sectionCascade"
            initial="hidden"
            animate="visible"
        >
            <m.div
                v-for="section in SECTIONS"
                :key="section.title"
                :variants="REVEAL_ITEM"
            >
                <Card>
                    <CardHeader>
                        <CardTitle>{{ section.title }}</CardTitle>
                        <CardDescription>
                            {{ section.description }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div v-if="section.lookup" class="sm:col-span-2">
                            <label
                                class="mb-2 block text-sm font-medium"
                                for="company-address-lookup"
                            >
                                Find an address
                            </label>
                            <AddressInput
                                id="company-address-lookup"
                                v-model="addressLookup"
                                v-model:text="addressLookupText"
                                placeholder="Start typing an address…"
                            />
                            <p class="mt-2 text-sm text-muted-foreground">
                                Selecting a suggestion fills the fields below,
                                including the coordinates.
                            </p>
                        </div>

                        <form.Field
                            v-for="spec in section.fields"
                            :key="spec.name"
                            :name="spec.name"
                            #default="{ field }"
                        >
                            <div
                                :class="spec.wide ? 'sm:col-span-2' : undefined"
                            >
                                <TextField
                                    :field="field"
                                    v-bind="toControlProps(spec)"
                                />
                            </div>
                        </form.Field>
                    </CardContent>
                </Card>
            </m.div>
        </m.div>

        <div class="flex items-center justify-end gap-3">
            <Button variant="outline" type="button" as-child>
                <Link :href="showCompany()">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="isSubmitting || !canSubmit">
                <Loader2Icon v-if="isSubmitting" class="animate-spin" />
                Save changes
            </Button>
        </div>
    </form>
</template>
