<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PostEditor from '@/modules/posts/components/PostEditor.vue';
import type { PostCategoryOption } from '@/modules/posts/types';
import { create, index } from '@/routes/posts';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Posts', href: index() },
            { title: 'New post', href: create() },
        ],
    },
});

defineProps<{
    categories: PostCategoryOption[];
}>();
</script>

<template>
    <Head title="New post" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">New post</h1>
            <p class="text-sm text-muted-foreground">
                Write it yourself, or let the assist panel draft one and edit
                what comes back. Nothing is saved until you submit.
            </p>
        </header>

        <PostEditor :categories="categories" />
    </div>
</template>
