<script setup lang="ts">
import { computed } from 'vue';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { campaignPlatformLabel } from '../helpers/campaignPresentation';
import type { CampaignPlatformContent } from '../types';

/**
 * The per-network ad variants the generator produced, one collapsible section
 * each.
 *
 * Read-only on purpose. `UpdateCampaignData` accepts only the master fields,
 * because a hand-edited platform variant would silently disagree with the five
 * quality scores that were computed from the generated one — re-running
 * generation is the supported way to change these.
 *
 * `adapted_primary_text`, the video script and the scene rows are rendered as
 * text nodes, never `v-html`: this is model output, which is untrusted input by
 * any other name (`OWASP/SKILL.md` — LLM02, output handling). Same reason
 * `image_concept.visual` is shown as prose rather than fetched as a URL.
 */
const { platforms } = defineProps<{
    platforms: Record<string, CampaignPlatformContent> | null;
}>();

/**
 * Keyed by the record's own key rather than `content.platform`: the two agree
 * today, but the key is what the backend actually indexes by, and an accordion
 * with duplicate values silently collapses two sections into one.
 *
 * `Object.entries` also absorbs the empty case without a branch — PHP
 * serializes an unset `platforms` array as `[]`, not `{}`.
 */
const entries = computed(() =>
    Object.entries(platforms ?? {}).map(([key, content]) => ({
        key,
        label: campaignPlatformLabel(content.platform || key),
        content,
    })),
);
</script>

<template>
    <p v-if="entries.length === 0" class="text-sm text-muted-foreground">
        No platform variants were generated for this campaign.
    </p>

    <Accordion v-else type="single" collapsible class="w-full">
        <AccordionItem
            v-for="entry in entries"
            :key="entry.key"
            :value="entry.key"
        >
            <AccordionTrigger>
                <span class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ entry.label }}</span>
                    <Badge variant="secondary" class="tabular-nums">
                        {{ entry.content.character_count }} chars
                    </Badge>
                    <Badge v-if="entry.content.video_package" variant="outline">
                        Video
                    </Badge>
                </span>
            </AccordionTrigger>

            <AccordionContent>
                <div class="flex flex-col gap-4">
                    <img
                        v-if="entry.content.image_url"
                        :src="entry.content.image_url"
                        :alt="`Generated artwork for ${entry.label}`"
                        loading="lazy"
                        class="max-w-sm rounded-lg border border-border"
                    />

                    <div class="flex flex-col gap-1">
                        <p class="text-sm font-medium">
                            {{ entry.content.headline }}
                        </p>
                        <p
                            v-if="entry.content.description"
                            class="text-xs text-muted-foreground"
                        >
                            {{ entry.content.description }}
                        </p>
                    </div>

                    <p class="text-sm whitespace-pre-wrap">
                        {{ entry.content.adapted_primary_text }}
                    </p>

                    <ul
                        v-if="entry.content.hashtags.length"
                        class="flex flex-wrap gap-1.5"
                    >
                        <li
                            v-for="tag in entry.content.hashtags"
                            :key="tag"
                            class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                        >
                            {{ tag }}
                        </li>
                    </ul>

                    <div
                        class="flex flex-col gap-1 rounded-lg border border-border bg-muted/40 p-3"
                    >
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Image concept
                        </p>
                        <p class="text-sm font-medium">
                            {{ entry.content.image_concept.title }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ entry.content.image_concept.visual }}
                        </p>
                    </div>

                    <div
                        v-if="entry.content.video_package"
                        class="flex flex-col gap-3 rounded-lg border border-border bg-muted/40 p-3"
                    >
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Video script ·
                            {{
                                entry.content.video_package
                                    .target_duration_seconds
                            }}s ·
                            {{ entry.content.video_package.creative_style }}
                        </p>

                        <p class="text-sm whitespace-pre-wrap">
                            {{ entry.content.video_package.clean_script }}
                        </p>

                        <ol
                            v-if="entry.content.video_package.scenes.length"
                            class="flex flex-col gap-2 border-l-2 border-border pl-4"
                        >
                            <li
                                v-for="(scene, i) in entry.content.video_package
                                    .scenes"
                                :key="i"
                                class="flex flex-col gap-0.5 text-sm"
                            >
                                <span
                                    class="text-xs font-medium text-muted-foreground tabular-nums"
                                >
                                    {{ scene.time_range }}
                                </span>
                                <span>{{ scene.action }}</span>
                                <span class="text-xs text-muted-foreground">
                                    On screen: {{ scene.on_screen_text }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    Voiceover: {{ scene.voiceover_line }}
                                </span>
                            </li>
                        </ol>

                        <p class="text-xs text-muted-foreground">
                            Sound:
                            {{ entry.content.video_package.sound_suggestion }}
                        </p>

                        <audio
                            v-if="
                                entry.content.video_package.voiceover_audio_url
                            "
                            controls
                            :src="
                                entry.content.video_package.voiceover_audio_url
                            "
                            class="w-full max-w-sm"
                        >
                            Your browser does not support audio playback.
                        </audio>
                    </div>
                </div>
            </AccordionContent>
        </AccordionItem>
    </Accordion>
</template>
