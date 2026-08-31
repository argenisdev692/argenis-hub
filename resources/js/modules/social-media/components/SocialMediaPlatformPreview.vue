<script setup lang="ts">
import { computed } from 'vue';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import type { SocialMediaPlatformContent } from '../types';

/**
 * The per-platform packages the generator produced, one collapsible section
 * each.
 *
 * Read-only on purpose. `UpdateSocialMediaContentData` accepts only the master
 * fields, because a hand-edited platform variant would silently disagree with
 * the quality scores that were computed from the generated one — re-running
 * generation is the supported way to change these.
 *
 * `adapted_content` and the thread tweets are rendered as text nodes, never
 * `v-html`: this is model output, which is untrusted input by any other name
 * (`OWASP/SKILL.md` — LLM02, output handling).
 */
const { platforms } = defineProps<{
    platforms: Record<string, SocialMediaPlatformContent> | null;
}>();

/**
 * Keyed by the record's own key rather than `content.platform`: the two agree
 * today, but the key is what the backend actually indexes by, and an accordion
 * with duplicate values silently collapses two sections into one.
 */
const entries = computed(() =>
    Object.entries(platforms ?? {}).map(([key, content]) => ({
        key,
        label: content.platform || key,
        content,
    })),
);
</script>

<template>
    <p v-if="entries.length === 0" class="text-sm text-muted-foreground">
        No platform variants were generated for this package.
    </p>

    <Accordion v-else type="single" collapsible class="w-full">
        <AccordionItem
            v-for="entry in entries"
            :key="entry.key"
            :value="entry.key"
        >
            <AccordionTrigger>
                <span class="flex items-center gap-2">
                    <span class="font-medium capitalize">{{
                        entry.label
                    }}</span>
                    <Badge variant="secondary" class="tabular-nums">
                        {{ entry.content.character_count }} chars
                    </Badge>
                    <Badge v-if="entry.content.is_thread" variant="outline">
                        Thread
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

                    <p class="text-sm whitespace-pre-wrap">
                        {{ entry.content.adapted_content }}
                    </p>

                    <ol
                        v-if="
                            entry.content.is_thread &&
                            entry.content.thread_tweets.length
                        "
                        class="flex flex-col gap-2 border-l-2 border-border pl-4"
                    >
                        <li
                            v-for="(tweet, i) in entry.content.thread_tweets"
                            :key="i"
                            class="text-sm whitespace-pre-wrap"
                        >
                            {{ tweet }}
                        </li>
                    </ol>

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
                        v-if="entry.content.video_package"
                        class="flex flex-col gap-2 rounded-lg border border-border bg-muted/40 p-3"
                    >
                        <p class="text-xs font-medium tracking-wide uppercase">
                            Video script ·
                            {{
                                entry.content.video_package
                                    .target_duration_seconds
                            }}s
                        </p>
                        <p class="text-sm whitespace-pre-wrap">
                            {{ entry.content.video_package.clean_script }}
                        </p>
                    </div>

                    <audio
                        v-if="entry.content.voiceover_audio_url"
                        controls
                        :src="entry.content.voiceover_audio_url"
                        class="w-full max-w-sm"
                    >
                        Your browser does not support audio playback.
                    </audio>
                </div>
            </AccordionContent>
        </AccordionItem>
    </Accordion>
</template>
