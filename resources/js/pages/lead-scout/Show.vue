<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    BanIcon,
    CopyIcon,
    RefreshCwIcon,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ChannelList from '@/modules/lead-scout/components/ChannelList.vue';
import DecisorCard from '@/modules/lead-scout/components/DecisorCard.vue';
import DraftEditor from '@/modules/lead-scout/components/DraftEditor.vue';
import LeadScoreReasons from '@/modules/lead-scout/components/LeadScoreReasons.vue';
import LeadTierBadge from '@/modules/lead-scout/components/LeadTierBadge.vue';
import { useLead } from '@/modules/lead-scout/composables/useLead';
import { useLeadMutations } from '@/modules/lead-scout/composables/useLeadMutations';
import { useAiSettings } from '@/modules/lead-scout/composables/useSettings';
import type { LeadOutreach } from '@/modules/lead-scout/types';
import { index } from '@/routes/lead-scout';

const { uuid } = defineProps<{
    uuid: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'LeadScout', href: index() }],
    },
});

const { lead, isLoading, refetch } = useLead(uuid);
const {
    rescoreLead,
    generateDraft,
    updateOutreach,
    recordReply,
    upsertContact,
    objectContact,
    updateChannel,
    suppressCompany,
} = useLeadMutations();
const { settings: aiSettings } = useAiSettings();

const companyName = computed(() => lead.value?.company.name ?? 'Lead');

const draftingOptions = computed(
    () => aiSettings.value?.purposes.drafting?.options ?? [],
);

const selectedOutreachUuid = ref<string | null>(null);

const selectedOutreach = computed<LeadOutreach | null>(() => {
    const outreaches = lead.value?.outreaches ?? [];

    return (
        outreaches.find((item) => item.uuid === selectedOutreachUuid.value)
        ?? outreaches.find((item) => item.stage === 'draft')
        ?? outreaches[0]
        ?? null
    );
});

watch(
    () => lead.value?.outreaches.map((item) => item.uuid).join(','),
    () => {
        if (
            selectedOutreachUuid.value !== null
            && !(lead.value?.outreaches ?? []).some(
                (item) => item.uuid === selectedOutreachUuid.value,
            )
        ) {
            selectedOutreachUuid.value = null;
        }
    },
);

// --- Draft editor state (client-only until saved). ---
const draftBody = ref('');
const draftProvider = ref('');
const draftModel = ref('');
const draftSubject = ref('');
const draftClaims = ref<string[]>([]);
const generating = ref(false);

watch(
    selectedOutreach,
    (outreach) => {
        draftBody.value = outreach?.draft_body ?? '';
        draftProvider.value = outreach?.ai_provider ?? '';
        draftModel.value = outreach?.ai_model ?? '';
    },
    { immediate: true },
);

async function startDraft(variant?: string): Promise<void> {
    generating.value = true;

    try {
        const response = await generateDraft.mutateAsync({
            uuid,
            payload: {
                ...(variant === undefined ? {} : { variant }),
                ...(draftProvider.value === ''
                    ? {}
                    : {
                          provider: draftProvider.value,
                          model: draftModel.value,
                      }),
            },
        });

        selectedOutreachUuid.value = response.data.uuid;
        draftSubject.value = response.meta.subject;
        draftClaims.value = response.meta.unconfirmed_claims;
    } finally {
        generating.value = false;
    }
}

function copyDraft(): void {
    const text = `${draftSubject.value}\n\n${draftBody.value}`;

    void navigator.clipboard.writeText(text).then(
        () => toast.success('Subject + body copied — send it by hand.'),
        () => toast.error('Copy failed. Select the text manually.'),
    );
}

function saveDraftBody(): void {
    if (selectedOutreach.value === null) {
        return;
    }

    updateOutreach.mutate({
        uuid: selectedOutreach.value.uuid,
        payload: { draft_body: draftBody.value },
    });
}

function markReady(): void {
    if (selectedOutreach.value === null) {
        return;
    }

    updateOutreach.mutate({
        uuid: selectedOutreach.value.uuid,
        payload: { stage: 'ready', draft_body: draftBody.value },
    });
}

// --- Manual send form (the system never sends). ---
const sendChannel = ref('');
const sendMedium = ref('');
const sendAck = ref(false);

function markSent(): void {
    if (selectedOutreach.value === null) {
        return;
    }

    updateOutreach.mutate({
        uuid: selectedOutreach.value.uuid,
        payload: {
            stage: 'sent',
            contact_channel_id: sendChannel.value,
            send_medium: sendMedium.value,
            acknowledge_pending_legal: sendAck.value,
        },
    });
}

function reply(outcome: 'interested' | 'not_interested' | 'unsubscribe'): void {
    if (selectedOutreach.value === null) {
        return;
    }

    if (
        outcome === 'unsubscribe'
        && !window.confirm(
            'Record an unsubscribe? This suppresses the company on every channel, permanently.',
        )
    ) {
        return;
    }

    recordReply.mutate({ uuid: selectedOutreach.value.uuid, outcome });
}

// --- Manual decisor form. ---
const contactForm = ref({
    full_name: '',
    role_title: '',
    role_category: 'executive',
    published_email: '',
    is_primary: false,
});

async function saveContact(): Promise<void> {
    await upsertContact.mutateAsync({
        companyUuid: uuid,
        payload: {
            full_name: contactForm.value.full_name,
            role_title: contactForm.value.role_title,
            role_category: contactForm.value.role_category,
            published_email:
                contactForm.value.published_email === ''
                    ? null
                    : contactForm.value.published_email,
            is_primary: contactForm.value.is_primary,
        },
    });

    contactForm.value = {
        full_name: '',
        role_title: '',
        role_category: 'executive',
        published_email: '',
        is_primary: false,
    };
}

const suppressReason = ref('');

function suppress(): void {
    const domain = lead.value?.company.domain;

    if (domain === undefined || suppressReason.value.trim() === '') {
        toast.error('A reason is required to suppress.');

        return;
    }

    suppressCompany
        .mutateAsync({ domain, reason: suppressReason.value.trim() })
        .then(() => refetch())
        .catch(() => undefined);
}
</script>

<template>
    <Head :title="companyName" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Button
                as-child
                variant="ghost"
                size="icon"
                aria-label="Back to bandeja"
            >
                <Link :href="index()">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                </Link>
            </Button>

            <div class="min-w-0 flex-1">
                <h1 class="truncate text-2xl font-semibold tracking-tight">
                    {{ companyName }}
                </h1>
                <p class="truncate text-sm text-muted-foreground">
                    {{ lead?.company.domain }}
                    {{ lead?.company.country ? `· ${lead.company.country}` : '' }}
                </p>
            </div>

            <LeadTierBadge :tier="lead?.score?.tier ?? null" />

            <PermissionGuard permission="UPDATE_LEAD_SCOUT">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="rescoreLead.isLoading.value"
                    @click="rescoreLead.mutate(uuid)"
                >
                    <RefreshCwIcon class="size-4" aria-hidden="true" />
                    Re-score
                </Button>
            </PermissionGuard>
        </div>

        <div
            v-if="isLoading"
            class="text-sm text-muted-foreground"
        >
            Loading lead…
        </div>

        <template v-else-if="lead !== null">
            <LeadScoreReasons
                :reasons="lead.reasons"
                :subscores="lead.score?.subscores ?? {}"
            />

            <Card v-if="lead.score?.discard_reason">
                <CardContent class="p-4 text-sm">
                    Discarded: {{ lead.score.discard_reason }}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Decision-makers</CardTitle>
                    <CardDescription>
                        Founders, executives and technical leads only — never
                        recruiters or staff. Opposed people vanish immediately.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <DecisorCard
                        v-for="decisor in lead.decisors"
                        :key="decisor.uuid"
                        :decisor="decisor"
                        :can-manage="true"
                        @make-primary="
                            (contactUuid) =>
                                upsertContact.mutate({
                                    companyUuid: uuid,
                                    contactUuid,
                                    payload: {
                                        full_name: decisor.full_name ?? '',
                                        role_title: decisor.role_title ?? '',
                                        role_category: decisor.role_category ?? 'executive',
                                        is_primary: true,
                                    },
                                })
                        "
                        @object="(contactUuid) => objectContact.mutate(contactUuid)"
                    />
                    <p
                        v-if="lead.decisors.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No decisor identified — use the form or offer channel below.
                    </p>

                    <PermissionGuard permission="UPDATE_LEAD_SCOUT">
                        <div class="grid gap-3 border-t border-border pt-3 md:grid-cols-2">
                            <div class="flex flex-col gap-1">
                                <Label for="contact-name">Add manually</Label>
                                <Input
                                    id="contact-name"
                                    v-model="contactForm.full_name"
                                    placeholder="Full name"
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label for="contact-title">Title (decisor only)</Label>
                                <Input
                                    id="contact-title"
                                    v-model="contactForm.role_title"
                                    placeholder="CEO, CTO, Founder…"
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label for="contact-email">Published email (optional)</Label>
                                <Input
                                    id="contact-email"
                                    v-model="contactForm.published_email"
                                    placeholder="name@company.tld"
                                />
                            </div>
                            <div class="flex items-end">
                                <Button
                                    size="sm"
                                    @click="saveContact"
                                >
                                    Save decisor
                                </Button>
                            </div>
                        </div>
                    </PermissionGuard>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Contact channels</CardTitle>
                    <CardDescription>
                        Ordered legal × commercial. Forms are filled by hand —
                        the system never submits them.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ChannelList
                        :channels="lead.channels"
                        :recommended-uuid="
                            lead.outreaches.find(
                                (item) => item.uuid === selectedOutreach?.uuid,
                            )?.contact_channel_id ?? null
                        "
                        :can-manage="true"
                        @mark-broken="(channelUuid) => updateChannel.mutate({ uuid: channelUuid, status: 'broken' })"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Outreach</CardTitle>
                    <CardDescription>
                        Drafts are generated, never sent. Copy, send by hand,
                        then mark the result.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="outreach in lead.outreaches"
                            :key="outreach.uuid"
                            :variant="
                                outreach.uuid === selectedOutreach?.uuid
                                    ? 'default'
                                    : 'outline'
                            "
                            size="sm"
                            @click="selectedOutreachUuid = outreach.uuid"
                        >
                            {{ outreach.stage }} · {{ outreach.variant ?? 'draft' }}
                        </Button>
                        <PermissionGuard permission="UPDATE_LEAD_SCOUT">
                            <Button
                                size="sm"
                                variant="secondary"
                                :disabled="generateDraft.isLoading.value"
                                @click="startDraft()"
                            >
                                New draft
                            </Button>
                        </PermissionGuard>
                    </div>

                    <DraftEditor
                        v-if="selectedOutreach !== null"
                        :body="draftBody"
                        :subject="draftSubject"
                        :provider="draftProvider"
                        :model="draftModel"
                        :options="draftingOptions"
                        :unconfirmed-claims="draftClaims"
                        :generating="generating"
                        @update:body="draftBody = $event"
                        @update:provider="draftProvider = $event"
                        @update:model="draftModel = $event"
                        @generate="startDraft()"
                        @copy="copyDraft"
                        @save="saveDraftBody"
                    />

                    <div
                        v-if="selectedOutreach !== null"
                        class="flex flex-wrap items-end gap-3 border-t border-border pt-3"
                    >
                        <PermissionGuard permission="UPDATE_LEAD_SCOUT">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="markReady"
                            >
                                Mark ready
                            </Button>

                            <div class="flex flex-col gap-1">
                                <Label for="send-channel">Channel used</Label>
                                <Select v-model="sendChannel">
                                    <SelectTrigger id="send-channel">
                                        <SelectValue placeholder="Channel" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="channel in lead.channels.filter(
                                                (item) => item.allowed && item.uuid !== null,
                                            )"
                                            :key="channel.uuid ?? channel.type"
                                            :value="channel.uuid ?? ''"
                                        >
                                            {{ channel.type }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="flex flex-col gap-1">
                                <Label for="send-medium">Medium</Label>
                                <Select v-model="sendMedium">
                                    <SelectTrigger id="send-medium">
                                        <SelectValue placeholder="Medium" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="email">
                                            Email
                                        </SelectItem>
                                        <SelectItem value="contact_form">
                                            Contact form
                                        </SelectItem>
                                        <SelectItem value="job_posting">
                                            Job posting reply
                                        </SelectItem>
                                        <SelectItem value="linkedin_manual">
                                            Network (manual)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <label class="flex items-center gap-2 text-xs">
                                <input
                                    v-model="sendAck"
                                    type="checkbox"
                                />
                                Rule pending — I confirm explicitly
                            </label>

                            <Button
                                size="sm"
                                @click="markSent"
                            >
                                Mark sent
                            </Button>

                            <Button
                                size="sm"
                                variant="outline"
                                @click="reply('interested')"
                            >
                                Interested
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                @click="reply('not_interested')"
                            >
                                Not interested
                            </Button>
                            <Button
                                size="sm"
                                variant="destructive"
                                @click="reply('unsubscribe')"
                            >
                                <BanIcon class="size-4" aria-hidden="true" />
                                Unsubscribe
                            </Button>
                        </PermissionGuard>
                    </div>

                    <div
                        v-if="selectedOutreach?.channel_warning"
                        class="text-xs text-muted-foreground"
                    >
                        Channel note: {{ selectedOutreach.channel_warning }}
                    </div>

                    <div
                        v-if="
                            (selectedOutreach?.opportunities.length ?? 0) > 0
                        "
                        class="flex flex-col gap-1 border-t border-border pt-3"
                    >
                        <p class="text-sm font-medium">
                            Opportunities
                        </p>
                        <ul class="flex flex-col gap-1 text-sm">
                            <li
                                v-for="opportunity in selectedOutreach?.opportunities ??
                                    []"
                                :key="opportunity.uuid"
                            >
                                {{ opportunity.type }} · {{ opportunity.status }}
                                <span
                                    v-if="
                                        opportunity.hours_per_month !== null
                                    "
                                >
                                    · {{ opportunity.hours_per_month }}h/mo
                                </span>
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

            <PermissionGuard permission="DELETE_LEAD_SCOUT">
                <Card>
                    <CardHeader>
                        <CardTitle>Suppress company</CardTitle>
                        <CardDescription>
                            Permanent, cross-channel, irreversible from here.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <Input
                            v-model="suppressReason"
                            placeholder="Reason (e.g. asked not to be contacted)"
                        />
                        <div>
                            <Button
                                variant="destructive"
                                size="sm"
                                @click="suppress"
                            >
                                Suppress
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </PermissionGuard>
        </template>

        <div
            v-else
            class="flex items-center gap-2 text-sm text-muted-foreground"
        >
            <CopyIcon class="size-4" aria-hidden="true" />
            Lead not found.
        </div>
    </div>
</template>
