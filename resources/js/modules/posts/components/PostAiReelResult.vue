<script setup lang="ts">
import { ClockIcon, MusicIcon } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import type { PostReelPackage } from '../types';
import PostCopyableBlock from './PostCopyableBlock.vue';

/**
 * The reel package: a shot list plus the copy that ships around it.
 *
 * The scenes render as an ordered timeline rather than a table because that is
 * how they get used — read top to bottom while filming, with the time range as
 * the anchor. A table would put `visual_prompt` in a 200px column.
 */

const { reel } = defineProps<{ reel: PostReelPackage }>();
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <Badge variant="secondary">
                <ClockIcon class="size-3" aria-hidden="true" />
                {{ reel.target_duration_seconds }}s
            </Badge>
            <Badge variant="outline">{{ reel.creative_style }}</Badge>
            <Badge variant="outline">
                <MusicIcon class="size-3" aria-hidden="true" />
                {{ reel.sound_suggestion }}
            </Badge>
        </div>

        <ol class="flex flex-col gap-3">
            <li
                v-for="(scene, index) in reel.scenes"
                :key="`${scene.time_range}-${index}`"
                class="rounded-lg border border-border bg-card p-3"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-medium">Scene {{ index + 1 }}</p>
                    <Badge variant="secondary" class="tabular-nums">
                        {{ scene.time_range }}
                    </Badge>
                </div>

                <dl class="mt-2 grid gap-1.5 text-xs">
                    <div class="flex gap-2">
                        <dt class="w-20 shrink-0 text-muted-foreground">
                            Action
                        </dt>
                        <dd>{{ scene.action }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-20 shrink-0 text-muted-foreground">
                            On screen
                        </dt>
                        <dd class="font-medium">{{ scene.on_screen_text }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-20 shrink-0 text-muted-foreground">
                            Voiceover
                        </dt>
                        <dd>{{ scene.voiceover_line }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-20 shrink-0 text-muted-foreground">
                            Visual
                        </dt>
                        <dd class="text-muted-foreground">
                            {{ scene.visual_prompt }}
                        </dd>
                    </div>
                </dl>
            </li>
        </ol>

        <PostCopyableBlock label="Clean script" :text="reel.clean_script" />
        <PostCopyableBlock label="TikTok caption" :text="reel.tiktok_caption" />

        <div v-if="reel.tiktok_hashtags.length" class="flex flex-col gap-1.5">
            <p class="text-xs font-medium text-muted-foreground">Hashtags</p>
            <div class="flex flex-wrap gap-1">
                <Badge
                    v-for="hashtag in reel.tiktok_hashtags"
                    :key="hashtag"
                    variant="secondary"
                >
                    {{ hashtag }}
                </Badge>
            </div>
        </div>

        <audio
            v-if="reel.voiceover_audio_url"
            :src="reel.voiceover_audio_url"
            controls
            class="w-full"
        >
            Your browser does not support audio playback.
        </audio>
    </div>
</template>
