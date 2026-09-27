<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { Button } from '@/components/ui/button';
import {
    useInvalidateProfiles,
    useProfiles,
} from '@/modules/cv-studio/composables/useProfiles';
import {
    usePendingRelations,
    useRelationMutations,
} from '@/modules/cv-studio/composables/useRelations';
import { index as postingsIndex } from '@/routes/cv-studio/postings';
import { policy, store, update } from '@/routes/cv-studio/profiles';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'CV Studio', href: postingsIndex() },
            { title: 'Profile settings' },
        ],
    },
});

const { profiles } = useProfiles();
const invalidateProfiles = useInvalidateProfiles();
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
        onSuccess: () => {
            toast.success('Opportunity policy updated.');
            void invalidateProfiles();
        },
        onError: () => toast.error('Policy values must sit in [0.1, 1].'),
    });
}

/**
 * The same form creates and edits. It used to be create-only: it never
 * prefilled from the selected profile, posted unconditionally, and relied on
 * the backend matching the slug — so editing meant retyping every field
 * including the exact slug, and a single changed character created a second
 * profile instead of editing the one on screen.
 */
const profileForm = useForm({
    name: '',
    slug: '',
    base_city: '',
    base_country: '',
    accepted_remote_scopes: ['remote_global', 'remote_eu', 'remote_pt_es'] as string[],
    stack_must: '',
    stack_reject: '',
    skill_floors: {} as Record<string, number>,
});

/** A profile already on disk ⇒ PUT by uuid; otherwise POST to create. */
const editingUuid = computed(() => selected.value?.uuid ?? null);

watch(
    selected,
    (profile) => {
        if (!profile) {
            return;
        }

        profileForm.name = profile.name;
        profileForm.slug = profile.slug;
        profileForm.base_city = profile.base_city ?? '';
        profileForm.base_country = profile.base_country ?? '';
        profileForm.accepted_remote_scopes = [...(profile.accepted_remote_scopes ?? [])];
        profileForm.stack_must = (profile.stack_must ?? []).join(', ');
        profileForm.stack_reject = (profile.stack_reject ?? []).join(', ');
        profileForm.skill_floors = { ...(profile.skill_floors ?? {}) };
    },
    { immediate: true },
);

/**
 * Per-skill defensibility, 0 absent → 4 anchor. Feeds the shortlist FILTER
 * only: a job whose required requirements all sit below the declared anchors is
 * skipped. It never changes a score, because the score is calibrated.
 */
const FLOOR_LEVELS = [
    { value: 0, label: 'Absent' },
    { value: 1, label: 'Junior' },
    { value: 2, label: 'Mid' },
    { value: 3, label: 'Solid' },
    { value: 4, label: 'Anchor' },
] as const;

const floorRows = computed(() =>
    Object.entries(profileForm.skill_floors).map(([skill, level]) => ({
        skill,
        level: Number(level) || 0,
    })),
);

function addFloorRow(): void {
    profileForm.skill_floors = {
        ...profileForm.skill_floors,
        '': 0,
    };
    floorDraft.value = '';
}

const floorDraft = ref('');

function setFloor(skill: string, level: number): void {
    const next = { ...profileForm.skill_floors };

    if (skill === '') {
        const name = floorDraft.value.trim();

        if (name === '') {
            return;
        }

        next[name] = level;
        floorDraft.value = '';
    } else {
        next[skill] = level;
    }

    profileForm.skill_floors = next;
}

function removeFloorRow(skill: string): void {
    const next = { ...profileForm.skill_floors };
    delete next[skill];
    profileForm.skill_floors = next;
}

function saveProfile(): void {
    const floors = Object.fromEntries(
        Object.entries(profileForm.skill_floors).filter(
            ([skill]) => skill.trim() !== '',
        ),
    );

    profileForm
        .transform((data) => ({
            ...data,
            skill_floors: floors,
            stack_must: data.stack_must
                .split(',')
                .map((term) => term.trim())
                .filter(Boolean),
            stack_reject: data.stack_reject
                .split(',')
                .map((term) => term.trim())
                .filter(Boolean),
        }))
        [editingUuid.value ? 'put' : 'post'](
            editingUuid.value ? update.url(editingUuid.value) : store.url(),
            {
                onSuccess: () => {
                    toast.success(
                        editingUuid.value
                            ? 'Profile updated.'
                            : 'Profile created.',
                    );
                    void invalidateProfiles();
                },
                onError: () =>
                    toast.error('Check the fields: a skill level must be 0-4.'),
            },
        );
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
            :aria-label="editingUuid ? 'Edit profile' : 'New profile'"
        >
            <h2 class="mb-2 text-sm font-semibold">
                {{ editingUuid ? 'Edit profile' : 'New profile' }}
            </h2>
            <form
                class="grid gap-3 md:grid-cols-2"
                @submit.prevent="saveProfile"
            >
                <label class="flex flex-col gap-1 text-sm">
                    Name
                    <input
                        v-model="profileForm.name"
                        type="text"
                        required
                        class="rounded-lg border border-border bg-background p-2"
                    />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Slug (lowercase, dashes)
                    <input
                        v-model="profileForm.slug"
                        type="text"
                        required
                        pattern="[a-z0-9\-]+"
                        class="rounded-lg border border-border bg-background p-2"
                    />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Base city
                    <input
                        v-model="profileForm.base_city"
                        type="text"
                        class="rounded-lg border border-border bg-background p-2"
                    />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Base country (ISO-2)
                    <input
                        v-model="profileForm.base_country"
                        type="text"
                        maxlength="8"
                        class="rounded-lg border border-border bg-background p-2"
                    />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Stack must (comma separated)
                    <input
                        v-model="profileForm.stack_must"
                        type="text"
                        class="rounded-lg border border-border bg-background p-2"
                    />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Stack reject (comma separated)
                    <input
                        v-model="profileForm.stack_reject"
                        type="text"
                        class="rounded-lg border border-border bg-background p-2"
                    />
                </label>

                <div
                    class="flex flex-col gap-2 md:col-span-2"
                    aria-label="Skill defensibility"
                >
                    <p class="text-sm font-medium">
                        Skill defensibility
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Filters the shortlist only — a job whose required
                        requirements all sit below your anchors is skipped. It
                        never changes a score. Set Laravel to Anchor and React
                        to Mid and the pipeline keeps the jobs anchored on your
                        strong half.
                    </p>

                    <ul
                        v-if="floorRows.length > 0"
                        class="flex flex-col gap-2"
                    >
                        <li
                            v-for="row in floorRows"
                            :key="row.skill || '__new__'"
                            class="flex items-center gap-3"
                        >
                            <span class="w-32 shrink-0 font-mono text-xs">
                                {{ row.skill || '(new skill)' }}
                            </span>
                            <input
                                :value="row.level"
                                type="range"
                                min="0"
                                max="4"
                                step="1"
                                :aria-label="`${row.skill || 'New skill'} defensibility`"
                                class="flex-1 accent-primary"
                                @input="
                                    setFloor(
                                        row.skill,
                                        Number(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        ),
                                    )
                                "
                            />
                            <span class="w-20 shrink-0 text-xs text-muted-foreground">
                                {{
                                    FLOOR_LEVELS.find(
                                        (level) => level.value === row.level,
                                    )?.label ?? row.level
                                }}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                :aria-label="`Remove ${row.skill || 'new skill'}`"
                                @click="removeFloorRow(row.skill)"
                            >
                                Remove
                            </Button>
                        </li>
                    </ul>

                    <div class="flex items-end gap-2">
                        <label class="flex flex-1 flex-col gap-1 text-sm">
                            Add a skill
                            <input
                                v-model="floorDraft"
                                type="text"
                                placeholder="Laravel"
                                class="rounded-lg border border-border bg-background p-2"
                            />
                        </label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addFloorRow"
                        >
                            Add
                        </Button>
                    </div>
                </div>

                <PermissionGuard
                    :permission="
                        editingUuid
                            ? 'UPDATE_STUDIO_PROFILES'
                            : 'CREATE_STUDIO_PROFILES'
                    "
                >
                    <Button
                        type="submit"
                        class="w-fit"
                        :disabled="profileForm.processing"
                    >
                        {{
                            editingUuid ? 'Save changes' : 'Create profile'
                        }}
                    </Button>
                </PermissionGuard>
            </form>
        </section>
    </div>
</template>
