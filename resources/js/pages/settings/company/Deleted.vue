<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { RotateCcwIcon } from '@lucide/vue';
import { ref } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    restore as restoreCompany,
    show as showCompany,
} from '@/routes/company';

/**
 * Shown at `/settings/company` while the company record is soft-deleted.
 *
 * The controller renders this instead of the record (and instead of a bare 404)
 * only when a trashed row exists and the operator can act on it. The restore is
 * the exact inverse of the danger-zone delete on `settings/company/Show.vue`.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Company', href: showCompany() }],
    },
});

const restoring = ref(false);

function restore(): void {
    router.patch(
        restoreCompany.url(),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                restoring.value = true;
            },
            onFinish: () => {
                restoring.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Company" />

    <h1 class="sr-only">Company settings</h1>

    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            title="Company"
            description="The record behind your invoices, emails and public site"
        />

        <div
            class="flex flex-col items-start gap-3 rounded-xl border border-border bg-muted/30 p-6"
        >
            <h2 class="text-sm font-semibold">Company profile deleted</h2>
            <p class="max-w-prose text-sm text-muted-foreground">
                It was soft-deleted, so nothing is lost. While it stays deleted,
                emails, invoices and the public site use the application's
                default name, URL and logos. Restore it to bring the record back
                exactly as it was.
            </p>

            <PermissionGuard permission="RESTORE_COMPANY_DATA">
                <Button
                    class="mt-1"
                    size="sm"
                    :disabled="restoring"
                    @click="restore"
                >
                    <RotateCcwIcon class="size-4" aria-hidden="true" />
                    Restore company profile
                </Button>

                <template #denied>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Ask an administrator to restore it, or re-run the
                        company seeder.
                    </p>
                </template>
            </PermissionGuard>
        </div>
    </div>
</template>
