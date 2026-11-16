<script setup lang="ts">
import { AudioLinesIcon, BotIcon, Loader2Icon } from '@lucide/vue';
import { computed, ref, useId, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useVideoEditMutations } from '../composables/useVideoEditMutations';
import {
    cutReasonLabel,
    formatDateTime,
    formatDurationMs,
    formatTimestampMs,
} from '../helpers/videoEditPresentation';
import type { AiCutReview } from '../types';

/**
 * The owner's say over every cut proposed for removal (OWASP LLM06). Nothing is
 * removed until this is answered: "Remove selected" renders with the ticked
 * cuts, "Keep everything" renders with none. Closing the dialog answers
 * nothing — the edit keeps waiting until the review expires.
 *
 * Two modes reach this dialog and the copy has to tell them apart. An AI edit
 * asks the model for retakes, misspoken words and misplaced takes; an auto edit
 * proposes the failed take before a spoken "PAUSA" straight from the transcript,
 * having sent nothing anywhere. Claiming "the AI" found a take it never saw would
 * make the review the one part of the pipeline nobody can trust, so `source`
 * decides who gets the credit and whose explanation is being read.
 *
 * Every string shown here is either transcript text or a written explanation, so
 * it is rendered as text only (OWASP LLM05).
 */
const { uuid, review, expiresAt, source, originalDurationMs = null, targetDurationMs = null } = defineProps<{
    uuid: string;
    review: AiCutReview;
    expiresAt: string | null;
    source: 'ai' | 'transcript';
    /** The recording's length, when known, so the ticked cuts can be projected. */
    originalDurationMs?: number | null;
    /** `ai_edit.target_duration_minutes`, in milliseconds. */
    targetDurationMs?: number | null;
}>();

const automaticCount = computed(
    () => review.cuts.filter((cut) => cut.origin !== 'ai').length,
);

const aiCount = computed(
    () => review.cuts.filter((cut) => cut.origin === 'ai').length,
);

const summary = computed(() => {
    const total = review.cuts.length;

    if (automaticCount.value > 0) {
        return `${total} ${total === 1 ? 'cut' : 'cuts'} will be applied: ${automaticCount.value} automatic (silence, hesitations, gaps, markers) and ${aiCount.value} from the AI. Nothing is removed until you choose.`;
    }

    return source === 'ai'
        ? `The AI found ${total} ${total === 1 ? 'passage' : 'passages'} to remove: spoken "PAUSA" markers, the takes before them and words that don't match your script. Nothing is cut until you choose.`
        : `The transcript shows ${total} ${total === 1 ? 'passage' : 'passages'} you flagged with a spoken "PAUSA". Nothing leaves this machine and nothing is cut until you choose.`;
});

const explanationLabel = computed(() => (source === 'ai' ? 'AI:' : 'Why:'));

const open = defineModel<boolean>('open', { default: false });

const { reviewVideoEditCuts } = useVideoEditMutations();

const uid = useId();
const selectedIds = ref<string[]>([]);
const pendingAnswer = ref<'remove' | 'keep' | null>(null);

// Confident proposals start ticked; the owner can untick anything.
watch(
    () => review.cuts.map((cut) => cut.id).join(','),
    () => {
        selectedIds.value = review.cuts
            .filter((cut) => cut.preselected)
            .map((cut) => cut.id);
    },
    { immediate: true },
);

const selectedCount = computed(() => selectedIds.value.length);

const removedMs = computed(() =>
    review.cuts
        .filter((cut) => selectedIds.value.includes(cut.id))
        .reduce((total, cut) => total + cut.duration_ms, 0),
);

/**
 * Where the video lands if the ticked cuts are the ones applied. Approximate
 * on purpose: seam trimming and the join air add or remove a few hundred
 * milliseconds per cut, and the point here is to answer "does this reach 10:00",
 * not to predict the frame.
 */
const projectedMs = computed<number | null>(() =>
    originalDurationMs === null
        ? null
        : Math.max(0, originalDurationMs - removedMs.value),
);

const targetShortfallMs = computed<number | null>(() => {
    if (targetDurationMs === null || projectedMs.value === null) {
        return null;
    }

    return Math.max(0, projectedMs.value - targetDurationMs);
});

const selectAllState = computed<boolean | 'indeterminate'>(() => {
    if (selectedCount.value === 0) {
        return false;
    }

    return selectedCount.value === review.cuts.length ? true : 'indeterminate';
});

const expiryNote = computed(() => {
    const deadline = formatDateTime(expiresAt);

    return deadline
        ? `If you don't answer by ${deadline}, the video renders with nothing removed.`
        : null;
});

function isSelected(cutId: string): boolean {
    return selectedIds.value.includes(cutId);
}

function toggleCut(cutId: string, checked: boolean | 'indeterminate'): void {
    selectedIds.value =
        checked === true
            ? [...new Set([...selectedIds.value, cutId])]
            : selectedIds.value.filter((id) => id !== cutId);
}

function toggleAll(checked: boolean | 'indeterminate'): void {
    selectedIds.value =
        checked === true ? review.cuts.map((cut) => cut.id) : [];
}

function confidenceLabel(confidence: number): string {
    return `${Math.round(confidence * 100)}% sure`;
}

async function answer(
    kind: 'remove' | 'keep',
    approvedCutIds: string[],
): Promise<void> {
    if (pendingAnswer.value !== null) {
        return;
    }

    pendingAnswer.value = kind;

    try {
        await reviewVideoEditCuts.mutateAsync({
            uuid,
            payload: { approved_cut_ids: approvedCutIds },
        });
        open.value = false;
    } catch {
        // The mutation already toasted the failure; the dialog stays open.
    } finally {
        pendingAnswer.value = null;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="flex max-h-[90dvh] flex-col gap-4 sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <BotIcon
                        v-if="source === 'ai'"
                        class="size-4"
                        aria-hidden="true"
                    />
                    <AudioLinesIcon v-else class="size-4" aria-hidden="true" />
                    Remove these parts of the video?
                </DialogTitle>
                <DialogDescription>
                    {{ summary }}
                </DialogDescription>
            </DialogHeader>

            <div
                class="flex flex-wrap items-center justify-between gap-2 text-sm"
            >
                <label
                    :for="`${uid}-all`"
                    class="flex min-h-6 cursor-pointer items-center gap-2"
                >
                    <Checkbox
                        :id="`${uid}-all`"
                        :model-value="selectAllState"
                        :disabled="pendingAnswer !== null"
                        @update:model-value="toggleAll"
                    />
                    Select all
                </label>
                <span class="text-muted-foreground tabular-nums">
                    {{ automaticCount }} automatic · {{ aiCount }} AI ·
                    {{ selectedCount }} selected ·
                    {{ formatDurationMs(removedMs) }} removed
                </span>
            </div>

            <p
                v-if="projectedMs !== null && targetDurationMs !== null"
                class="text-xs text-muted-foreground tabular-nums"
            >
                Lands at {{ formatDurationMs(projectedMs) }} · target
                {{ formatDurationMs(targetDurationMs) }} ·
                {{
                    targetShortfallMs === 0
                        ? 'on target'
                        : `${formatDurationMs(targetShortfallMs)} short`
                }}
            </p>

            <!--
                One table for every cut: the deterministic ones and the model's.
                "Automatic" is a separate badge because a silence being removed
                is not a judgement call, and the owner should be able to see at a
                glance which rows came from a dictionary or a threshold.
            -->
            <div class="-mx-1 min-h-0 flex-1 overflow-auto px-1">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead
                        class="sticky top-0 bg-card text-xs text-muted-foreground"
                    >
                        <tr>
                            <th scope="col" class="w-8 py-2">
                                <span class="sr-only">Include</span>
                            </th>
                            <th scope="col" class="py-2 text-left">Start</th>
                            <th scope="col" class="py-2 text-left">End</th>
                            <th scope="col" class="py-2 text-left">Reason</th>
                            <th scope="col" class="py-2 text-left">Removed</th>
                            <th scope="col" class="py-2 text-left">Kept</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="cut in review.cuts"
                            :key="cut.id"
                            :class="[
                                'border-t border-border align-top',
                                isSelected(cut.id) && 'bg-muted/50',
                            ]"
                        >
                            <td class="py-2">
                                <Checkbox
                                    :id="`${uid}-${cut.id}`"
                                    :aria-label="`Include ${cut.text || cutReasonLabel(cut.reason)}`"
                                    :model-value="isSelected(cut.id)"
                                    :disabled="pendingAnswer !== null"
                                    @update:model-value="
                                        toggleCut(cut.id, $event)
                                    "
                                />
                            </td>
                            <td class="py-2 tabular-nums">
                                {{ formatTimestampMs(cut.start_ms) }}
                            </td>
                            <td class="py-2 tabular-nums">
                                {{ formatTimestampMs(cut.end_ms) }}
                            </td>
                            <td class="py-2">
                                <div class="flex flex-wrap items-center gap-1">
                                    <Badge variant="secondary">
                                        {{ cutReasonLabel(cut.reason) }}
                                    </Badge>
                                    <Badge
                                        v-if="cut.origin !== 'ai'"
                                        variant="outline"
                                    >
                                        Automatic
                                    </Badge>
                                    <span
                                        class="text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{ confidenceLabel(cut.confidence) }}
                                    </span>
                                </div>
                                <p
                                    v-if="cut.explanation"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    <span class="font-medium">{{
                                        explanationLabel
                                    }}</span>
                                    {{ cut.explanation }}
                                </p>
                            </td>
                            <td class="py-2">
                                <p class="wrap-break-word">
                                    <span
                                        v-if="cut.context_before"
                                        class="text-muted-foreground"
                                        >…{{ cut.context_before }}
                                    </span>
                                    <span
                                        :class="[
                                            'rounded px-0.5 font-medium',
                                            isSelected(cut.id) &&
                                                'bg-destructive/10 text-destructive line-through',
                                        ]"
                                        >{{ cut.text || '—' }}</span
                                    >
                                    <span
                                        v-if="cut.context_after"
                                        class="text-muted-foreground"
                                    >
                                        {{ cut.context_after }}…</span
                                    >
                                </p>
                            </td>
                            <td class="py-2 text-muted-foreground">
                                {{ cut.keep_text ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="expiryNote" class="text-xs text-muted-foreground">
                {{ expiryNote }}
            </p>

            <DialogFooter class="gap-2">
                <Button
                    variant="outline"
                    :disabled="pendingAnswer !== null"
                    @click="answer('keep', [])"
                >
                    <Loader2Icon
                        v-if="pendingAnswer === 'keep'"
                        class="animate-spin"
                        aria-hidden="true"
                    />
                    Keep everything
                </Button>
                <Button
                    autofocus
                    variant="destructive"
                    :disabled="pendingAnswer !== null || selectedCount === 0"
                    @click="answer('remove', selectedIds)"
                >
                    <Loader2Icon
                        v-if="pendingAnswer === 'remove'"
                        class="animate-spin"
                        aria-hidden="true"
                    />
                    Yes, remove {{ selectedCount }}
                    {{ selectedCount === 1 ? 'cut' : 'cuts' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
