<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { scoreTextClass, scoreTone } from '../helpers/socialMediaPresentation';
import type {
    SocialMediaAiDetectionRisk,
    SocialMediaResearchSource,
} from '../types';

/**
 * The evaluator's qualitative output — the half of the quality loop that is not
 * a number.
 *
 * Sits beside `SocialMediaQualityScores` rather than inside it: the meters
 * answer "did this pass", these answer "why, and what would move it". Mixing
 * them produced one card nobody read to the bottom of.
 *
 * Every string here is model output rendered as a text node, never `v-html`
 * (`OWASP/SKILL.md` — LLM02, output handling). `research_sources[].source` is
 * shown as text rather than linked for the same reason: it is a provider-
 * supplied string, not a URL this app validated.
 */
const {
    eeatAnalysis = null,
    optimizationSuggestions = null,
    researchSources = null,
    aiDetectionRisk = null,
    qualityWarningMessage = null,
    iterationsRequired = null,
} = defineProps<{
    eeatAnalysis?: Record<string, string[]> | null;
    optimizationSuggestions?: string[] | null;
    researchSources?: SocialMediaResearchSource[] | null;
    aiDetectionRisk?: SocialMediaAiDetectionRisk | null;
    qualityWarningMessage?: string | null;
    iterationsRequired?: number | null;
}>();

/**
 * `eeat_analysis` arrives as `{ experience_signals: [...], … }`. The key is
 * turned into a heading here rather than hard-coding the four names, so a
 * fifth signal added server-side renders instead of vanishing.
 */
const eeatSections = computed(() =>
    Object.entries(eeatAnalysis ?? {})
        .filter(([, signals]) => signals.length > 0)
        .map(([key, signals]) => ({
            key,
            label: key.replace(/_/g, ' '),
            signals,
        })),
);

const hasContent = computed(
    () =>
        eeatSections.value.length > 0 ||
        (optimizationSuggestions?.length ?? 0) > 0 ||
        (researchSources?.length ?? 0) > 0 ||
        aiDetectionRisk !== null,
);
</script>

<template>
    <Card v-if="hasContent">
        <CardHeader>
            <CardTitle>AI insights</CardTitle>
            <CardDescription>
                What the evaluator found, and what it would change.
                <span v-if="iterationsRequired !== null">
                    Settled after
                    {{ iterationsRequired }}
                    {{ iterationsRequired === 1 ? 'iteration' : 'iterations' }}.
                </span>
            </CardDescription>
        </CardHeader>

        <CardContent class="flex flex-col gap-6">
            <p
                v-if="qualityWarningMessage"
                class="rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-sm"
                role="status"
            >
                {{ qualityWarningMessage }}
            </p>

            <div v-if="aiDetectionRisk" class="flex flex-col gap-1">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-medium">AI detection risk</h3>
                    <Badge variant="outline">
                        <span
                            :class="
                                cn(
                                    'tabular-nums',
                                    scoreTextClass(
                                        scoreTone(aiDetectionRisk.value, false),
                                    ),
                                )
                            "
                        >
                            {{ aiDetectionRisk.value }}
                        </span>
                        · {{ aiDetectionRisk.label }}
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ aiDetectionRisk.explanation }}
                </p>
            </div>

            <div
                v-if="optimizationSuggestions?.length"
                class="flex flex-col gap-2"
            >
                <h3 class="text-sm font-medium">Optimization suggestions</h3>
                <ul class="flex list-disc flex-col gap-1 pl-5">
                    <li
                        v-for="(suggestion, i) in optimizationSuggestions"
                        :key="i"
                        class="text-sm text-muted-foreground"
                    >
                        {{ suggestion }}
                    </li>
                </ul>
            </div>

            <div v-if="eeatSections.length" class="flex flex-col gap-3">
                <h3 class="text-sm font-medium">E-E-A-T signals</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="section in eeatSections"
                        :key="section.key"
                        class="flex flex-col gap-1"
                    >
                        <h4
                            class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ section.label }}
                        </h4>
                        <ul class="flex list-disc flex-col gap-0.5 pl-5">
                            <li
                                v-for="(signal, i) in section.signals"
                                :key="i"
                                class="text-sm"
                            >
                                {{ signal }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div v-if="researchSources?.length" class="flex flex-col gap-2">
                <h3 class="text-sm font-medium">Research sources</h3>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="(source, i) in researchSources"
                        :key="i"
                        class="rounded-lg border border-border bg-muted/40 px-3 py-2"
                    >
                        <p class="text-sm font-medium break-words">
                            {{ source.source }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ source.key_insight }}
                        </p>
                    </li>
                </ul>
            </div>
        </CardContent>
    </Card>
</template>
