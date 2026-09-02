<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { PencilIcon, Trash2Icon } from '@lucide/vue';
import { m } from 'motion-v';
import { computed, ref } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import ConfirmModal from '@/common/table/ConfirmModal.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { MOTION_STAGGER_TIGHT, staggerContainer } from '@/lib/motion';
import CompanyDetailList from '@/modules/company/components/CompanyDetailList.vue';
import CompanyLogoGrid from '@/modules/company/components/CompanyLogoGrid.vue';
import CompanyLogosDialog from '@/modules/company/components/CompanyLogosDialog.vue';
import CompanySectionCard from '@/modules/company/components/CompanySectionCard.vue';
import CompanySectionDialog from '@/modules/company/components/CompanySectionDialog.vue';
import CompanySocialLinks from '@/modules/company/components/CompanySocialLinks.vue';
import {
    addressDetails,
    contactDetails,
    fiscalDetails,
    identityDetails,
} from '@/modules/company/helpers/companyDetails';
import {
    ADDRESS_FIELDS,
    CONTACT_FIELDS,
    FISCAL_FIELDS,
    IDENTITY_FIELDS,
    SOCIAL_FIELDS,
} from '@/modules/company/helpers/companyFields';
import { formatDateTime } from '@/modules/company/helpers/companyPresentation';
import type { CompanyProfile } from '@/modules/company/types';
import {
    destroy as destroyCompany,
    edit as editCompany,
    show as showCompany,
} from '@/routes/company';

/**
 * The company record, read-first.
 *
 * A singleton — there is no index and no `{uuid}`. Each section is editable in
 * place through a dialog; the full-page form at `/settings/company/edit` remains
 * for anyone who would rather work through every field in one pass, and both
 * post the same payload to the same route.
 *
 * The record can be soft-deleted from the danger zone below (SUPER_ADMIN only,
 * `DELETE_COMPANY_DATA`). While it is deleted every branding surface falls back
 * to the app defaults and this screen is replaced by `settings/company/Deleted`,
 * which offers the matching restore.
 */
const { company } = defineProps<{ company: CompanyProfile }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Company', href: showCompany() }],
    },
});

const confirmDeleteOpen = ref(false);

function deleteCompany(): void {
    router.delete(destroyCompany.url(), { preserveScroll: true });
}

/**
 * Which dialog is open, as one value rather than six booleans — two dialogs can
 * never be open at once, and a single `null`-able key makes that unrepresentable
 * instead of merely unlikely.
 */
type DialogKey =
    'identity' | 'contact' | 'address' | 'fiscal' | 'socials' | 'logos';

const activeDialog = ref<DialogKey | null>(null);

function dialogModel(key: DialogKey) {
    return computed({
        get: () => activeDialog.value === key,
        set: (isOpen: boolean) => {
            activeDialog.value = isOpen ? key : null;
        },
    });
}

const identityOpen = dialogModel('identity');
const contactOpen = dialogModel('contact');
const addressOpen = dialogModel('address');
const fiscalOpen = dialogModel('fiscal');
const socialsOpen = dialogModel('socials');
const logosOpen = dialogModel('logos');

/**
 * The operational stagger, not the marketing one: this screen is a tool someone
 * opens to check a number, and a slow cascade is a tax at that frequency.
 */
const sectionCascade = staggerContainer(MOTION_STAGGER_TIGHT);

const lastUpdated = computed(() => formatDateTime(company.updated_at));

const identity = computed(() => identityDetails(company));
const contact = computed(() => contactDetails(company));
const address = computed(() => addressDetails(company));
const fiscal = computed(() => fiscalDetails(company));
</script>

<template>
    <Head title="Company" />

    <h1 class="sr-only">Company settings</h1>

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Company"
                description="The record behind your invoices, emails and public site"
            />

            <PermissionGuard permission="UPDATE_COMPANY_DATA">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="editCompany()">
                        <PencilIcon class="size-4" aria-hidden="true" />
                        Edit all fields
                    </Link>
                </Button>
            </PermissionGuard>
        </div>

        <m.div
            class="flex flex-col gap-6"
            :variants="sectionCascade"
            initial="hidden"
            animate="visible"
        >
            <CompanySectionCard
                title="Identity"
                description="How the company is named across the product."
                edit-label="Edit identity"
                @edit="activeDialog = 'identity'"
            >
                <CompanyDetailList :details="identity" />
            </CompanySectionCard>

            <CompanySectionCard
                title="Contact"
                description="Where customers reach you."
                edit-label="Edit contact details"
                @edit="activeDialog = 'contact'"
            >
                <CompanyDetailList :details="contact" />
            </CompanySectionCard>

            <CompanySectionCard
                title="Address"
                description="Used on invoices and the public site."
                edit-label="Edit address"
                @edit="activeDialog = 'address'"
            >
                <CompanyDetailList :details="address" />
            </CompanySectionCard>

            <CompanySectionCard
                title="Fiscal & banking"
                description="Tax identifiers and payment details for invoices."
                edit-label="Edit fiscal and banking details"
                @edit="activeDialog = 'fiscal'"
            >
                <CompanyDetailList :details="fiscal" />
            </CompanySectionCard>

            <CompanySectionCard
                title="Social profiles"
                description="Rendered in the site footer and email signatures."
                edit-label="Edit social profiles"
                @edit="activeDialog = 'socials'"
            >
                <CompanySocialLinks :company="company" />
            </CompanySectionCard>

            <CompanySectionCard
                title="Brand marks"
                description="PNG, JPEG or WebP, up to 4 MB each."
                edit-label="Replace brand marks"
                @edit="activeDialog = 'logos'"
            >
                <CompanyLogoGrid :logos="company.logos" />
            </CompanySectionCard>
        </m.div>

        <p v-if="lastUpdated" class="text-xs text-muted-foreground">
            Last updated
            <time :datetime="company.updated_at ?? undefined">
                {{ lastUpdated }}
            </time>
        </p>

        <PermissionGuard permission="DELETE_COMPANY_DATA">
            <section
                class="mt-2 flex flex-col gap-3 rounded-xl border border-destructive/30 bg-destructive/5 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-sm font-semibold text-destructive">
                        Delete company profile
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Emails, invoices and the public site fall back to app
                        defaults until it is restored.
                    </p>
                </div>

                <Button
                    variant="destructive"
                    size="sm"
                    class="w-fit shrink-0"
                    @click="confirmDeleteOpen = true"
                >
                    <Trash2Icon class="size-4" aria-hidden="true" />
                    Delete
                </Button>
            </section>
        </PermissionGuard>
    </div>

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete this company profile?"
        description="It will be soft-deleted — you can restore it afterwards. While deleted, emails, invoices and the public site fall back to app defaults."
        confirm-label="Delete"
        @confirm="deleteCompany"
    >
        <div
            class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
        >
            <p class="font-medium">{{ company.company_name }}</p>
            <p v-if="company.legal_name" class="text-muted-foreground">
                {{ company.legal_name }}
            </p>
        </div>
    </ConfirmModal>

    <CompanySectionDialog
        v-model:open="identityOpen"
        :company="company"
        :fields="IDENTITY_FIELDS"
        title="Identity"
        description="How the company is named across the product."
        success-message="Identity updated."
    />

    <CompanySectionDialog
        v-model:open="contactOpen"
        :company="company"
        :fields="CONTACT_FIELDS"
        title="Contact"
        description="Where customers reach you."
        success-message="Contact details updated."
    />

    <CompanySectionDialog
        v-model:open="addressOpen"
        :company="company"
        :fields="ADDRESS_FIELDS"
        title="Address"
        description="Used on invoices and the public site."
        success-message="Address updated."
    />

    <CompanySectionDialog
        v-model:open="fiscalOpen"
        :company="company"
        :fields="FISCAL_FIELDS"
        title="Fiscal & banking"
        description="Tax identifiers and payment details for invoices."
        success-message="Fiscal details updated."
    />

    <CompanySectionDialog
        v-model:open="socialsOpen"
        :company="company"
        :fields="SOCIAL_FIELDS"
        title="Social profiles"
        description="Full profile URLs, including https://."
        success-message="Social profiles updated."
    />

    <CompanyLogosDialog v-model:open="logosOpen" />
</template>
