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
import { scoreTextClass, scoreTone } from '../helpers/campaignPresentation';
import type { CampaignAiDetectionRisk, CampaignResearchSource } from '../types';

/**
 * The evaluator's qualitative output — the half of the quality loop that is not
 * a number — plus the two media-buying artefacts the generator produces
 * alongside the copy.
 *
 * Sits beside `CampaignQualityScores` rather than inside it: the meters answer
 * "did this pass", these answer "why, and what would move it". Mixing them
 * produced one card nobody read to the bottom of.
 *
 * `targetingSuggestions` lives here rather than on the form because
 * `UpdateCampaignData` does not accept it — it is generated advice for whoever
 * sets the campaign up in Ads Manager, not an editable field.
 *
 * Every string here is model output rendered as a text node, never `v-html`
 * (`OWASP/SKILL.md` — LLM02, output handling). `research_sources[].source` is
 * shown as text rather than linked for the same reason: it is a
 * provider-supplied string, not a URL this app validated.
 */
const {
    optimizationSuggestions = null,
    targetingSuggestions = null,
    researchSources = null,
    tavilyDataUsed = null,
    aiDetectionRisk = null,
    qualityWarningMessage = null,
    iterationsRequired = null,
} = defineProps<{
    optimizationSuggestions?: string[] | null;
    targetingSuggestions?: string[] | null;
    researchSources?: CampaignResearchSource[] | null;
    tavilyDataUsed?: string[] | null;
    aiDetectionRisk?: CampaignAiDetectionRisk | null;
    qualityWarningMessage?: string | null;
    iterationsRequired?: number | null;
}>();

const hasContent = computed(
    () =>
        (optimizationSuggestions?.length ?? 0) > 0 ||
        (targetingSuggestions?.length ?? 0) > 0 ||
        (researchSources?.length ?? 0) > 0 ||
        (tavilyDataUsed?.length ?? 0) > 0 ||
        aiDetectionRisk !== null ||
        qualityWarningMessage !== null,
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

            <div
                v-if="targetingSuggestions?.length"
                class="flex flex-col gap-2"
            >
                <h3 class="text-sm font-medium">Targeting suggestions</h3>
                <p class="text-xs text-muted-foreground">
                    For whoever builds the ad set — not stored on the campaign
                    itself.
                </p>
                <ul class="flex list-disc flex-col gap-1 pl-5">
                    <li
                        v-for="(suggestion, i) in targetingSuggestions"
                        :key="i"
                        class="text-sm text-muted-foreground"
                    >
                        {{ suggestion }}
                    </li>
                </ul>
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

            <div v-if="tavilyDataUsed?.length" class="flex flex-col gap-2">
                <h3 class="text-sm font-medium">Trend data used</h3>
                <ul class="flex flex-wrap gap-1.5">
                    <li
                        v-for="(item, i) in tavilyDataUsed"
                        :key="i"
                        class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                    >
                        {{ item }}
                    </li>
                </ul>
            </div>
        </CardContent>
    </Card>
</template>
