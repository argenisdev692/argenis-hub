<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PostEditor from '@/modules/posts/components/PostEditor.vue';
import PostStatusBadge from '@/modules/posts/components/PostStatusBadge.vue';
import { formatDateTime } from '@/modules/posts/helpers/postPresentation';
import type { PostCategoryOption, PostDetail } from '@/modules/posts/types';
import { index } from '@/routes/posts';

const { post, categories } = defineProps<{
    post: PostDetail;
    categories: PostCategoryOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Posts', href: index() }],
    },
});
</script>

<template>
    <Head :title="post.post_title" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ post.post_title }}
                </h1>
                <PostStatusBadge
                    :status="post.post_status"
                    :deleted-at="post.deleted_at"
                />
            </div>

            <p class="text-sm text-muted-foreground">
                <span class="font-mono">/{{ post.post_title_slug }}</span>
                <span v-if="post.updated_at">
                    · last edited {{ formatDateTime(post.updated_at) }}</span
                >
            </p>
        </header>

        <PostEditor :post="post" :categories="categories" />
    </div>
</template>
