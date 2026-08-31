<script setup lang="ts">
import { computed, useId } from 'vue';
import { cn } from '@/lib/utils';
import {
    scoreBarClass,
    scoreTextClass,
    scoreTone,
} from '../helpers/postPresentation';

/**
 * The four quality signals a generated draft carries, as labelled meters.
 *
 * `ai_detection_risk` is the odd one out and is marked as such: it is the only
 * score where a high number is bad, so it gets its own scale rather than a
 * footnote nobody reads. Rendering them together is the point — a 92 SEO score
 * beside a 78 detection risk is a different decision than a 92 on its own.
 */

const {
    seoScore = null,
    eeatScore = null,
    humanWritingIndex = null,
    aiDetectionRisk = null,
    compact = false,
} = defineProps<{
    seoScore?: number | null;
    eeatScore?: number | null;
    humanWritingIndex?: number | null;
    aiDetectionRisk?: number | null;
    /** Drops the meters and shows numerals only — for tight spots like a table. */
    compact?: boolean;
}>();

const uid = useId();

const meters = computed(() => [
    { key: 'seo', label: 'SEO', value: seoScore, higherIsBetter: true },
    { key: 'eeat', label: 'E-E-A-T', value: eeatScore, higherIsBetter: true },
    {
        key: 'human',
        label: 'Human index',
        value: humanWritingIndex,
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
                compact ? 'grid-cols-2' : 'grid-cols-2 sm:grid-cols-4',
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
