<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useQuery } from '@pinia/colada';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { Button } from '@/components/ui/button';
import { httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    usePendingRelations,
    useRelationMutations,
} from '@/modules/cv-studio/composables/useRelations';
import type { StudioProfile } from '@/modules/cv-studio/types';
import { index as postingsIndex } from '@/routes/cv-studio/postings';
import { index, policy, store } from '@/routes/cv-studio/profiles';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'CV Studio', href: postingsIndex() },
            { title: 'Profile settings' },
        ],
    },
});

type ProfilePage = {
    data: (StudioProfile & { rules?: Record<string, unknown> })[];
};

const { data: profilesData } = useQuery<ProfilePage>({
    key: () => ['studio-profiles'],
    query: () => httpJson<ProfilePage>(toUrl(index())),
    staleTime: 1000 * 60 * 5,
    gcTime: 1000 * 60 * 10,
});

const profiles = computed(() => profilesData.value?.data ?? []);
const selectedUuid = ref<string | null>(null);

const selected = computed(
    () =>
        profiles.value.find((profile) => profile.uuid === selectedUuid.value) ??
        profiles.value[0] ??
        null,
);

watch(
    profiles,
    (list) => {
        if (selectedUuid.value === null && list.length > 0) {
            selectedUuid.value = list[0]?.uuid ?? null;
        }
    },
    { immediate: true },
);

const policyForm = useForm({
    channels: {} as Record<
        string,
        { value: number; grade: string; source: string }
    >,
    neutral: false,
});

watch(
    selected,
    (profile) => {
        const opportunity = (profile?.rules?.opportunity ?? {}) as {
            channel?: Record<
                string,
                { value: number; grade: string; source: string }
            >;
            neutral?: boolean;
        };

        policyForm.channels = { ...(opportunity.channel ?? {}) };
        policyForm.neutral = opportunity.neutral ?? false;
    },
    { immediate: true },
);

function savePolicy(): void {
    if (!selected.value?.uuid) {
        return;
    }

    policyForm.put(policy.url(selected.value.uuid), {
        onSuccess: () => toast.success('Opportunity policy updated.'),
        onError: () => toast.error('Policy values must sit in [0.1, 1].'),
    });
}

const createForm = useForm({
    name: '',
    slug: '',
    accepted_remote_scopes: ['remote_global', 'remote_eu', 'remote_pt_es'],
    stack_must: '',
    stack_reject: '',
});

function createProfile(): void {
    createForm
        .transform((data) => ({
            ...data,
            stack_must: data.stack_must
                .split(',')
                .map((term) => term.trim())
                .filter(Boolean),
            stack_reject: data.stack_reject
                .split(',')
                .map((term) => term.trim())
                .filter(Boolean),
        }))
        .post(store.url(), {
            onSuccess: () => {
                toast.success('Profile saved.');
                createForm.reset();
            },
        });
}

const { relations } = usePendingRelations();
const { confirmRelation, rejectRelation } = useRelationMutations();

async function confirm(uuid: string): Promise<void> {
    try {
        await confirmRelation.mutateAsync(uuid);
    } catch {
        return;
    }
}

async function reject(uuid: string): Promise<void> {
    try {
        await rejectRelation.mutateAsync(uuid);
    } catch {
        return;
    }
}
</script>

<template>
    <Head title="CV Studio · Profile settings" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Search profiles
            </h1>
            <p class="text-sm text-muted-foreground">
                Gates, stack lock, geography and seniority live per profile —
                a new profile needs no code and inherits nothing.
            </p>
        </header>

        <section
            v-if="selected"
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Active profile gates"
        >
            <h2 class="mb-2 text-sm font-semibold">{{ selected.name }}</h2>
            <dl class="grid gap-2 text-sm md:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">Accepted scopes</dt>
                    <dd>
                        {{
                            (selected.accepted_remote_scopes ?? []).join(', ') ||
                            '— none (runs are blocked until set)'
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Stack must</dt>
                    <dd>{{ (selected.stack_must ?? []).join(', ') || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Stack reject</dt>
                    <dd>
                        {{ (selected.stack_reject ?? []).join(', ') || '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Base</dt>
                    <dd>
                        {{ selected.base_city ?? '—' }}
                        {{ selected.base_country ?? '' }}
                    </dd>
                </div>
            </dl>
        </section>

        <section
            v-if="selected"
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Opportunity policy"
        >
            <h2 class="mb-1 text-sm font-semibold">Opportunity policy</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                Editable policy values with evidence grade and source — never
                statistics. Own outcomes replace a value only past 30
                applications and 3 positives.
            </p>

            <div class="flex flex-col gap-2">
                <div
                    v-for="(entry, channel) in policyForm.channels"
                    :key="channel"
                    class="flex items-center gap-2 text-sm"
                >
                    <span class="w-32 shrink-0 font-mono text-xs">{{
                        channel
                    }}</span>
                    <input
                        v-model.number="entry.value"
                        type="number"
                        min="0.1"
                        max="1"
                        step="0.05"
                        class="w-20 rounded-lg border border-border bg-background p-1.5"
                        :aria-label="`${channel} factor`"
                    />
                    <span class="text-xs text-muted-foreground">
                        grade {{ entry.grade }} · {{ entry.source }}
                    </span>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="policyForm.neutral"
                        type="checkbox"
                        class="size-4"
                    />
                    Neutral mode — opportunity factor 1, pure fit order
                </label>

                <PermissionGuard permission="UPDATE_STUDIO_PROFILES">
                    <Button
                        class="w-fit"
                        :disabled="policyForm.processing"
                        @click="savePolicy"
                    >
                        Save policy
                    </Button>
                </PermissionGuard>
            </div>
        </section>

        <section
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Relations inbox"
        >
            <h2 class="mb-2 text-sm font-semibold">
                Skill relations inbox ({{ relations.length }} pending)
            </h2>
            <p class="mb-2 text-xs text-muted-foreground">
                The model may propose a relation — only your confirmation
                grants credit.
            </p>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="relation in relations"
                    :key="relation.uuid"
                    class="flex items-center justify-between gap-2 text-sm"
                >
                    <span>
                        {{ relation.from_skill }} → {{ relation.to_skill }}
                        <span class="text-xs text-muted-foreground">
                            ({{ relation.kind }} · {{ relation.origin }})
                        </span>
                    </span>
                    <span class="flex gap-1">
                        <Button
                            size="sm"
                            variant="outline"
                            @click="confirm(relation.uuid)"
                        >
                            Confirm
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="reject(relation.uuid)"
                        >
                            Reject
                        </Button>
                    </span>
                </li>
            </ul>
        </section>

        <section
            class="rounded-xl border border-border bg-card p-4"
            aria-label="New profile"
        >
            <h2 class="mb-2 text-sm font-semibold">New profile</h2>
            <form
                class="grid gap-3 md:grid-cols-2"
                @submit.prevent="createProfile"
            >
                <label class="flex flex-col gap-1 text-sm">
                    Name
                    <input
                        v-model="createForm.name"
                        type="text"
                        required
                        class="rounded-lg border border-border bg-background p-2"
                    />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Slug (lowercase, dashes)
                    <input
                        v-model="createForm.slug"
                        type="text"
                        required
                        pattern="[a-z0-9-]+"
                        class="rounded-lg border border-border bg-background p-2"
                    />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Stack must (comma separated)
                    <input
                        v-model="createForm.stack_must"
                        type="text"
                        class="rounded-lg border border-border bg-background p-2"
                    />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Stack reject (comma separated)
                    <input
                        v-model="createForm.stack_reject"
                        type="text"
                        class="rounded-lg border border-border bg-background p-2"
                    />
                </label>
                <PermissionGuard permission="CREATE_STUDIO_PROFILES">
                    <Button
                        type="submit"
                        class="w-fit"
                        :disabled="createForm.processing"
                    >
                        Create profile
                    </Button>
                </PermissionGuard>
            </form>
        </section>
    </div>
</template>
