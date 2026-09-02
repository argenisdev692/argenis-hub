<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, ImageIcon } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import BlogCategoryStatusBadge from '@/modules/blog-categories/components/BlogCategoryStatusBadge.vue';
import { formatDateTime } from '@/modules/blog-categories/helpers/blogCategoryPresentation';
import type { BlogCategoryDetail } from '@/modules/blog-categories/types';
import { index } from '@/routes/blog-categories';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Blog categories', href: index() }],
    },
});

const { blogCategory } = defineProps<{
    blogCategory: BlogCategoryDetail;
}>();

const title = computed(
    () => blogCategory.blog_category_name?.trim() || 'Untitled category',
);

/**
 * Assembled rather than rendered as a fixed table: a row that has never been
 * edited has `updated_at === created_at`, and one that is active has no
 * suspension date. Empty entries are dropped instead of showing an em dash on
 * every line a healthy row does not have.
 */
const timeline = computed(() =>
    [
        { label: 'Created', value: formatDateTime(blogCategory.created_at) },
        {
            label: 'Last updated',
            value: formatDateTime(blogCategory.updated_at),
        },
        { label: 'Suspended', value: formatDateTime(blogCategory.deleted_at) },
    ].filter((entry): entry is { label: string; value: string } =>
        Boolean(entry.value),
    ),
);
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
        <div class="flex items-center gap-3">
            <Button
                as-child
                variant="ghost"
                size="icon"
                aria-label="Back to blog categories"
            >
                <Link :href="index()">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />
                </Link>
            </Button>

            <div class="flex flex-1 flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
            </div>

            <BlogCategoryStatusBadge :deleted-at="blogCategory.deleted_at" />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Details</CardTitle>
                <CardDescription>
                    How this category appears on the blog and on the public
                    landing page.
                </CardDescription>
            </CardHeader>

            <CardContent class="flex flex-col gap-6">
                <div class="flex items-start gap-4">
                    <img
                        v-if="blogCategory.image_url"
                        :src="blogCategory.image_url"
                        :alt="`Image for ${title}`"
                        class="size-24 rounded-lg object-cover"
                        loading="lazy"
                    />
                    <span
                        v-else
                        class="flex size-24 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                        role="img"
                        aria-label="No image"
                    >
                        <ImageIcon class="size-6" aria-hidden="true" />
                    </span>

                    <p
                        class="flex-1 text-sm"
                        :class="
                            blogCategory.blog_category_description
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                    >
                        {{
                            blogCategory.blog_category_description ||
                            'No description.'
                        }}
                    </p>
                </div>

                <dl class="grid gap-3 sm:grid-cols-2">
                    <div v-for="entry in timeline" :key="entry.label">
                        <dt class="text-xs text-muted-foreground">
                            {{ entry.label }}
                        </dt>
                        <dd class="text-sm">{{ entry.value }}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    </div>
</template>
