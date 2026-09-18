<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useMutation } from '@pinia/colada';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { parse } from '@/routes/cv-studio/cvs';
import { index as postingsIndex } from '@/routes/cv-studio/postings';
import { confirm } from '@/routes/cv-studio/structures';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'CV Studio', href: postingsIndex() },
            { title: 'CV structure' },
        ],
    },
});

const cvUuid = ref('');
const parsedUuid = ref<string | null>(null);

const parseCvMutation = useMutation({
    mutation: () =>
        httpJson<{ data: { uuid: string } }>(toUrl(parse()), {
            method: 'POST',
            body: cvUuid.value ? { cv_uuid: cvUuid.value } : {},
        }),
    onSuccess(response: { data: { uuid: string } }) {
        parsedUuid.value = response.data.uuid;
        toast.success('CV parsed into addressable rows.');
    },
    onError() {
        toast.error('Failed to parse the CV.');
    },
});

function parseCv(): void {
    void parseCvMutation.mutateAsync(undefined);
}

const confirmForm = useForm({});

function confirmStructure(): void {
    if (!parsedUuid.value) {
        return;
    }

    confirmForm.put(confirm.url(parsedUuid.value));
}
</script>

<template>
    <Head title="CV Studio · CV structure" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">CV structure</h1>
            <p class="text-sm text-muted-foreground">
                Parse a stored CV into addressable sections, entries, bullets
                and skills — the rows tailoring re-orders. Raw text stays the
                immutable source; parsing is re-runnable. Nothing is usable
                until you confirm it below.
            </p>
        </header>

        <form
            class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4"
            @submit.prevent="parseCv"
        >
            <label class="flex flex-col gap-1 text-sm">
                CV uuid (empty uses your primary CV)
                <input
                    v-model="cvUuid"
                    type="text"
                    class="rounded-lg border border-border bg-background p-2 font-mono text-xs"
                    placeholder="01a0b58f-…"
                />
            </label>
            <Button
                type="submit"
                class="w-fit"
                :disabled="parseCvMutation.isLoading.value"
            >
                Parse structure
            </Button>
        </form>

        <section
            v-if="parsedUuid"
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Confirm structure"
        >
            <p class="font-mono text-xs">{{ parsedUuid }}</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Review the parsed rows, then confirm to unlock rewriting and
                tailoring.
            </p>
            <Button
                class="mt-3 w-fit"
                :disabled="confirmForm.processing"
                @click="confirmStructure"
            >
                Confirm structure
            </Button>
        </section>
    </div>
</template>
