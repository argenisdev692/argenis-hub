<script setup lang="ts">
import { computed, useId } from 'vue';
import { cn } from '@/lib/utils';
import {
    scoreBarClass,
    scoreTextClass,
    scoreTone,
} from '../helpers/socialMediaPresentation';

/**
 * The five quality signals the generation loop optimises against, plus the AI
 * detection risk it reports alongside them, as labelled meters.
 *
 * These are the numbers the loop actually iterates on: `ContentQualityEvaluator`
 * re-runs generation until all five clear their thresholds or the iteration
 * budget is spent. Showing them together is the point — a 91 virality beside a
 * 52 ROI is a different editorial decision than either number alone.
 *
 * `aiDetectionRisk` is the odd one out and is marked as such: it is the only
 * score where a high number is bad, so it gets its own scale rather than a
 * footnote nobody reads.
 */

const {
    humanWritingIndex = null,
    viralityScore = null,
    engagementScore = null,
    roiScore = null,
    trendAlignment = null,
    aiDetectionRisk = null,
    compact = false,
} = defineProps<{
    humanWritingIndex?: number | null;
    viralityScore?: number | null;
    engagementScore?: number | null;
    roiScore?: number | null;
    trendAlignment?: number | null;
    /** `ai_detection_risk.value` — the object's 0–100 number, not the object. */
    aiDetectionRisk?: number | null;
    /** Drops the meters and shows numerals only — for tight spots like a table. */
    compact?: boolean;
}>();

const uid = useId();

const meters = computed(() => [
    {
        key: 'human',
        label: 'Human index',
        value: humanWritingIndex,
        higherIsBetter: true,
    },
    {
        key: 'virality',
        label: 'Virality',
        value: viralityScore,
        higherIsBetter: true,
    },
    {
        key: 'engagement',
        label: 'Engagement',
        value: engagementScore,
        higherIsBetter: true,
    },
    { key: 'roi', label: 'ROI', value: roiScore, higherIsBetter: true },
    {
        key: 'trend',
        label: 'Trend fit',
        value: trendAlignment,
        higherIsBetter: true,
    },
    {
        key: 'risk',
        label: 'AI detection risk',
        value: aiDetectionRisk,
        higherIsBetter: false,
    },
]);
</script>

<template>
    <dl
        :class="
            cn(
                'grid gap-x-4 gap-y-3',
                compact
                    ? 'grid-cols-3'
                    : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
            )
        "
    >
        <div
            v-for="meter in meters"
            :key="meter.key"
            class="flex flex-col gap-1"
        >
            <dt
                :id="`${uid}-${meter.key}`"
                class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ meter.label }}
            </dt>

            <dd
                :class="
                    cn(
                        'text-lg font-semibold tabular-nums',
                        scoreTextClass(
                            scoreTone(meter.value, meter.higherIsBetter),
                        ),
                    )
                "
            >
                {{ meter.value === null ? '—' : meter.value }}
            </dd>

            <div
                v-if="!compact && meter.value !== null"
                role="meter"
                :aria-labelledby="`${uid}-${meter.key}`"
                :aria-valuenow="meter.value"
                aria-valuemin="0"
                aria-valuemax="100"
                class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
            >
                <div
                    :class="
                        cn(
                            'h-full rounded-full transition-[width] duration-300',
                            scoreBarClass(
                                scoreTone(meter.value, meter.higherIsBetter),
                            ),
                        )
                    "
                    :style="{ width: `${meter.value}%` }"
                />
            </div>
        </div>
    </dl>
</template>
