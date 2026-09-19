<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeftIcon, ExternalLinkIcon } from '@lucide/vue';
import { computed } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { Button } from '@/components/ui/button';
import FitScoreBadge from '@/modules/cv-studio/components/FitScoreBadge.vue';
import OpportunityChips from '@/modules/cv-studio/components/OpportunityChips.vue';
import PostingStatusBadge from '@/modules/cv-studio/components/PostingStatusBadge.vue';
import TailorChatPanel from '@/modules/cv-studio/components/TailorChatPanel.vue';
import { usePostingMutations } from '@/modules/cv-studio/composables/usePostingMutations';
import {
    bandLabel,
    formatDate,
    remoteScopeLabel,
} from '@/modules/cv-studio/helpers/studioPresentation';
import type { StudioPosting, StudioScore } from '@/modules/cv-studio/types';
import { index, resolve, unlink } from '@/routes/cv-studio/postings';
import { dismiss } from '@/routes/cv-studio/postings/requirements';

type GateRow = {
    gate_code: string;
    passed: boolean;
    reason_code: string | null;
};

type RequirementRow = {
    uuid: string;
    canonical_name: string;
    tag: string;
    nature: string;
};

const {
    posting,
    requirements = [],
    gates = [],
    score = null,
} = defineProps<{
    posting: StudioPosting;
    requirements?: RequirementRow[];
    gates?: GateRow[];
    score?: StudioScore | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'CV Studio', href: index() },
            { title: posting.title },
        ],
    },
});

const { rescorePosting } = usePostingMutations();

const hBar = computed(() => Math.round(score?.h ?? 0));
const sBar = computed(() => Math.round(score?.s ?? 0));
const dBar = computed(() => Math.round(score?.d ?? 0));

function goBack(): void {
    router.visit(index().url);
}

function applyDirect(): void {
    if (posting.canonical_url) {
        window.open(posting.canonical_url, '_blank', 'noopener');
    }
}

function resolveSource(): void {
    router.post(resolve(posting.uuid).url);
}

function unlinkSource(): void {
    router.post(unlink(posting.uuid).url);
}

function dismissRequirement(requirement: RequirementRow): void {
    router.post(dismiss({ uuid: posting.uuid, ruuid: requirement.uuid }).url);
}

async function rescore(): Promise<void> {
    try {
        await rescorePosting.mutateAsync({
            uuid: posting.uuid,
            input: {
                cv_skills: [],
                similarities: {
                    title_cosine: 0,
                    responsibility_cosine: 0,
                },
                signals: {
                    experience: null,
                    location: null,
                    education: null,
                    language: null,
                },
                cap_context: {
                    readable: true,
                    credential_ok: true,
                    evidence_ok: true,
                },
            },
        });
    } catch {
        return;
    }
}

const failedGates = computed(() => gates.filter((gate) => !gate.passed));
</script>

<template>
    <Head :title="`CV Studio · ${posting.title}`" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <Button variant="ghost" class="w-fit" @click="goBack">
            <ArrowLeftIcon class="size-4" aria-hidden="true" />
            Back to postings
        </Button>

        <header class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ posting.title }}
                </h1>
                <FitScoreBadge
                    :score="posting.total_score"
                    :band="posting.band"
                />
            </div>
            <p class="text-sm text-muted-foreground">
                {{ posting.employer_name ?? 'Unknown employer' }} ·
                {{ posting.location_text ?? 'No location' }} ·
                {{ remoteScopeLabel(posting.remote_scope) }}
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <PostingStatusBadge
                    :status="posting.status"
                    :deleted-at="posting.deleted_at"
                />
                <span
                    v-if="posting.cap_reason"
                    class="text-xs text-muted-foreground"
                >
                    Capped: {{ posting.cap_reason }} — see uncapped value in the
                    score breakdown.
                </span>
            </div>
        </header>

        <section
            v-if="failedGates.length > 0"
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Gate results"
        >
            <h2 class="mb-2 text-sm font-semibold">Gate results</h2>
            <ul class="flex flex-col gap-1 text-sm">
                <li v-for="gate in failedGates" :key="gate.gate_code">
                    <span class="font-mono">{{ gate.gate_code }}</span>
                    failed — {{ gate.reason_code ?? 'no reason recorded' }}.
                    Gate-failed postings are counted, never scored.
                </li>
            </ul>
        </section>

        <section
            v-if="score"
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Score breakdown"
        >
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold">
                    Match report · {{ bandLabel(score.band) }}
                </h2>
                <span class="text-xs text-muted-foreground">{{
                    score.heuristic_label
                }}</span>
            </div>

            <dl class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-muted p-3">
                    <dt class="text-xs text-muted-foreground">Skills (H)</dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ hBar }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted p-3">
                    <dt class="text-xs text-muted-foreground">Semantic (S)</dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ sBar }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted p-3">
                    <dt class="text-xs text-muted-foreground">
                        Deterministic (D)
                    </dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ dBar }}
                    </dd>
                </div>
            </dl>

            <p class="mt-3 text-sm">
                Total
                <strong class="tabular-nums">{{ score.total_score }}</strong>
                <span class="text-muted-foreground">
                    (uncapped {{ score.raw_score }} · rules v{{
                        score.rules_version
                    }})
                </span>
            </p>
        </section>

        <section
            class="rounded-xl border border-border bg-card p-4"
            aria-label="Requirements"
        >
            <h2 class="mb-2 text-sm font-semibold">Requirements</h2>
            <ul v-if="requirements.length > 0" class="flex flex-col gap-2">
                <li
                    v-for="requirement in requirements"
                    :key="requirement.uuid"
                    class="flex items-center justify-between gap-2 text-sm"
                >
                    <span>
                        {{ requirement.canonical_name }}
                        <span class="text-xs text-muted-foreground">
                            · {{ requirement.tag }} · {{ requirement.nature }}
                        </span>
                    </span>
                    <PermissionGuard permission="UPDATE_STUDIO_POSTINGS">
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="dismissRequirement(requirement)"
                        >
                            Dismiss
                        </Button>
                    </PermissionGuard>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No requirements extracted yet.
            </p>
        </section>

        <TailorChatPanel :posting-uuid="posting.uuid" />

        <section class="flex flex-wrap gap-2" aria-label="Posting actions">
            <PermissionGuard permission="VIEW_STUDIO_POSTINGS">
                <Button
                    v-if="posting.canonical_url"
                    variant="outline"
                    @click="applyDirect"
                >
                    <ExternalLinkIcon class="size-4" aria-hidden="true" />
                    Apply direct
                </Button>
            </PermissionGuard>

            <PermissionGuard permission="UPDATE_STUDIO_POSTINGS">
                <Button variant="outline" @click="resolveSource">
                    Resolve to source
                </Button>
                <Button
                    v-if="posting.apply_destination"
                    variant="outline"
                    @click="unlinkSource"
                >
                    Unlink resolution
                </Button>
                <Button variant="outline" @click="rescore">
                    Rescore from stored rows
                </Button>
            </PermissionGuard>
        </section>

        <OpportunityChips :components="{}" />
        <p class="text-xs text-muted-foreground">
            Scored {{ formatDate(posting.created_at) }}. Opportunity factors
            refresh with the apply-priority pass.
        </p>
    </div>
</template>
