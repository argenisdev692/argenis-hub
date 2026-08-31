<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { PostSocialCopy } from '../types';
import PostCopyableBlock from './PostCopyableBlock.vue';

const { copy } = defineProps<{ copy: PostSocialCopy }>();
</script>

<template>
    <div class="flex flex-col gap-4">
        <PostCopyableBlock label="LinkedIn post" :text="copy.linkedin_post" />
        <PostCopyableBlock label="Short caption" :text="copy.social_caption" />

        <div v-if="copy.hashtags.length" class="flex flex-col gap-1.5">
            <p class="text-xs font-medium text-muted-foreground">Hashtags</p>
            <div class="flex flex-wrap gap-1">
                <Badge
                    v-for="hashtag in copy.hashtags"
                    :key="hashtag"
                    variant="secondary"
                >
                    {{ hashtag }}
                </Badge>
            </div>
        </div>
    </div>
</template>
