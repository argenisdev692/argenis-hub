<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, PencilIcon, SparklesIcon } from '@lucide/vue';
import { computed } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import PostContentView from '@/modules/posts/components/PostContentView.vue';
import PostQualityScores from '@/modules/posts/components/PostQualityScores.vue';
import PostStatusBadge from '@/modules/posts/components/PostStatusBadge.vue';
import {
    formatDateTime,
    postAuthorName,
} from '@/modules/posts/helpers/postPresentation';
import type { PostDetail } from '@/modules/posts/types';
import { edit, index } from '@/routes/posts';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Posts', href: index() }],
    },
});

const { post } = defineProps<{
    post: PostDetail;
}>();

/**
 * The timeline reads differently per status, so it is assembled rather than
 * rendered as a fixed table: a draft has no publish date, and a scheduled post
 * has one that has not happened yet. Empty entries are dropped instead of
 * showing an em dash for every row a draft does not have.
 */
const timeline = computed(() =>
    [
        { label: 'Created', value: formatDateTime(post.created_at) },
        { label: 'Last edited', value: formatDateTime(post.updated_at) },
        { label: 'Scheduled for', value: formatDateTime(post.scheduled_at) },
        { label: 'Published', value: formatDateTime(post.published_at) },
        { label: 'Suspended', value: formatDateTime(post.deleted_at) },
    ].filter((entry): entry is { label: string; value: string } =>
        Boolean(entry.value),
    ),
);

const metadata = computed(() =>
    [
        { label: 'Meta title', value: post.meta_title },
        { label: 'Meta description', value: post.meta_description },
        { label: 'Meta keywords', value: post.meta_keywords },
    ].filter((entry): entry is { label: string; value: string } =>
        Boolean(entry.value),
    ),
);

const hasScores = computed(
    () =>
        post.seo_score !== null ||
        post.eeat_score !== null ||
        post.human_writing_index !== null ||
        post.ai_detection_risk !== null,
);
</script>

<template>
    <Head :title="post.post_title" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-2">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ post.post_title }}
                    </h1>
                    <PostStatusBadge
                        :status="post.post_status"
                        :deleted-at="post.deleted_at"
                    />
                    <Badge v-if="post.is_ai_generated" variant="secondary">
                        <SparklesIcon class="size-3" aria-hidden="true" />
                        AI assisted
                    </Badge>
                </div>

                <p class="text-sm text-muted-foreground">
                    <span class="font-mono">/{{ post.post_title_slug }}</span>
                    <span> · by {{ postAuthorName(post) }}</span>
                    <span v-if="post.category">
                        · {{ post.category.blog_category_name }}</span
                    >
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="index()">
                        <ArrowLeftIcon class="size-4" aria-hidden="true" />
                        Back
                    </Link>
                </Button>

                <!-- A suspended post has no edit route worth offering: restore
                     it from the list first. -->
                <PermissionGuard
                    v-if="!post.deleted_at"
                    permission="UPDATE_POSTS"
                >
                    <Button as-child>
                        <Link :href="edit(post.uuid)">
                            <PencilIcon class="size-4" aria-hidden="true" />
                            Edit
                        </Link>
                    </Button>
                </PermissionGuard>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <img
                    v-if="post.cover_image_url"
                    :src="post.cover_image_url"
                    :alt="`Cover image for ${post.post_title}`"
                    class="aspect-video w-full rounded-xl border border-border object-cover"
                />

                <Card>
                    <CardHeader v-if="post.post_excerpt">
                        <CardDescription class="text-base text-foreground">
                            {{ post.post_excerpt }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <PostContentView :content="post.post_content" />
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Timeline</CardTitle>
                    </CardHeader>

                    <CardContent>
                        <dl class="flex flex-col gap-3">
                            <div
                                v-for="entry in timeline"
                                :key="entry.label"
                                class="flex flex-col gap-0.5"
                            >
                                <dt
                                    class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ entry.label }}
                                </dt>
                                <dd class="text-sm">{{ entry.value }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card v-if="metadata.length">
                    <CardHeader>
                        <CardTitle>Search metadata</CardTitle>
                    </CardHeader>

                    <CardContent>
                        <dl class="flex flex-col gap-3">
                            <div
                                v-for="entry in metadata"
                                :key="entry.label"
                                class="flex flex-col gap-0.5"
                            >
                                <dt
                                    class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ entry.label }}
                                </dt>
                                <dd class="text-sm break-words">
                                    {{ entry.value }}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card v-if="hasScores">
                    <CardHeader>
                        <CardTitle>Quality scores</CardTitle>
                        <CardDescription>
                            Recorded when the draft was generated
                            <template v-if="post.ai_provider">
                                by {{ post.ai_provider }} </template
                            >. Editing the body does not re-score it.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <PostQualityScores
                            compact
                            :seo-score="post.seo_score"
                            :eeat-score="post.eeat_score"
                            :human-writing-index="post.human_writing_index"
                            :ai-detection-risk="post.ai_detection_risk"
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
