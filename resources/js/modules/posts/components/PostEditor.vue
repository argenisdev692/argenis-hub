<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { SaveIcon } from '@lucide/vue';
import { computed } from 'vue';
import { AppField, FileDropzone, TextField } from '@/common/form';
// By path, not through the barrel — see the note in `common/form/index.ts`.
import RichTextInput from '@/common/form/RichTextInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/posts';
import { usePostForm } from '../composables/usePostForm';
import { postStatusPresentation } from '../helpers/postPresentation';
import { isPostStatus, POST_STATUS_VALUES } from '../schemas/postFormSchema';
import type { PostCategoryOption, PostDetail } from '../types';
import PostAiAssistPanel from './PostAiAssistPanel.vue';
import PostQualityScores from './PostQualityScores.vue';

/**
 * The whole write surface, shared by Create and Edit.
 *
 * The form is built HERE rather than in the pages and handed down, because
 * passing a TanStack form across a component boundary flattens it to
 * `AnyFormApi` and takes every field name's type with it (see the note in
 * `common/form/TextField.vue`). Owning it means `<form.Field name="…">` stays
 * checked against `PostFormValues`, and the two pages stay two lines each.
 *
 * `post` is the only thing that differs between them: null creates, a record
 * edits. `usePostForm` reads it once to pick the endpoint.
 */

const { post = null, categories } = defineProps<{
    post?: PostDetail | null;
    categories: readonly PostCategoryOption[];
}>();

const { form, applyGeneratedDraft } = usePostForm({ post });

const isSubmitting = form.useStore((state) => state.isSubmitting);
const status = form.useStore((state) => state.values.status);
const categoryUuid = form.useStore((state) => state.values.category_uuid);
const isAiGenerated = form.useStore((state) => state.values.is_ai_generated);

// One selector per score rather than one returning an object: `useStore`
// compares the selected value by reference, so a fresh object literal would
// register as "changed" on every keystroke anywhere in the form.
const seoScore = form.useStore((state) => state.values.seo_score);
const eeatScore = form.useStore((state) => state.values.eeat_score);
const humanWritingIndex = form.useStore(
    (state) => state.values.human_writing_index,
);
const aiDetectionRisk = form.useStore(
    (state) => state.values.ai_detection_risk,
);

/**
 * reka-ui's `Select` cannot represent "no selection" with an empty string, so
 * the optional category rides a sentinel that never reaches the wire.
 */
const NO_CATEGORY = '__none__';

const categoryModel = computed(() => categoryUuid.value ?? NO_CATEGORY);

function onCategoryChange(value: unknown): void {
    if (typeof value !== 'string') {
        return;
    }

    form.setFieldValue('category_uuid', value === NO_CATEGORY ? null : value);
}

const existingCoverUrl = computed(() => post?.cover_image_url ?? null);
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="form.handleSubmit()">
        <PostAiAssistPanel
            :categories="categories"
            :default-category-uuid="categoryUuid"
            @apply="applyGeneratedDraft"
        />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Article</CardTitle>
                        <CardDescription>
                            The title becomes the H1 and the slug, so the body
                            below only offers H2 and smaller.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-5">
                        <form.Field name="title" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Title"
                                required
                                placeholder="Why headless CMS wins in 2026"
                            />
                        </form.Field>

                        <form.Field name="content" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Content"
                                required
                                description="Bold, italic, headings, quotes, lists and links. Ctrl+B / Ctrl+I."
                                #default="{ control, invalid }"
                            >
                                <RichTextInput
                                    :id="control.id"
                                    :model-value="String(field.state.value)"
                                    :aria-describedby="
                                        control['aria-describedby']
                                    "
                                    :aria-invalid="invalid"
                                    aria-label="Post content"
                                    placeholder="Open with a hook — a statistic, a contrarian take, a specific failure."
                                    @update:model-value="field.handleChange"
                                />
                            </AppField>
                        </form.Field>

                        <form.Field name="excerpt" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Excerpt"
                                multiline
                                :rows="3"
                                description="Shown in listings and used as the fallback meta description. Up to 500 characters."
                                placeholder="One or two sentences that make the click worth it."
                            />
                        </form.Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Search metadata</CardTitle>
                        <CardDescription>
                            Leave blank to fall back to the title and excerpt.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-5">
                        <form.Field name="meta_title" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Meta title"
                                placeholder="Up to 255 characters"
                            />
                        </form.Field>

                        <form.Field
                            name="meta_description"
                            #default="{ field }"
                        >
                            <TextField
                                :field="field"
                                label="Meta description"
                                multiline
                                :rows="2"
                                placeholder="Up to 500 characters"
                            />
                        </form.Field>

                        <form.Field name="meta_keywords" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Meta keywords"
                                description="Comma separated."
                                placeholder="headless cms, jamstack, content api"
                            />
                        </form.Field>
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Publishing</CardTitle>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-5">
                        <form.Field name="status" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Status"
                                required
                                #default="{ control }"
                            >
                                <Select
                                    :model-value="field.state.value"
                                    @update:model-value="
                                        (value) =>
                                            isPostStatus(value) &&
                                            field.handleChange(value)
                                    "
                                >
                                    <SelectTrigger
                                        v-bind="control"
                                        class="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="value in POST_STATUS_VALUES"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{
                                                postStatusPresentation(value)
                                                    .label
                                            }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </AppField>
                        </form.Field>

                        <form.Field
                            v-if="status === 'scheduled'"
                            name="scheduled_at"
                            #default="{ field }"
                        >
                            <AppField
                                :field="field"
                                label="Publish at"
                                required
                                description="Must be in the future. The scheduler publishes it on the next run."
                                #default="{ control }"
                            >
                                <Input
                                    v-bind="control"
                                    type="datetime-local"
                                    :model-value="field.state.value ?? ''"
                                    @blur="field.handleBlur"
                                    @update:model-value="
                                        (value) =>
                                            field.handleChange(
                                                String(value) || null,
                                            )
                                    "
                                />
                            </AppField>
                        </form.Field>

                        <form.Field name="category_uuid" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Category"
                                #default="{ control }"
                            >
                                <Select
                                    :model-value="categoryModel"
                                    @update:model-value="onCategoryChange"
                                >
                                    <SelectTrigger
                                        v-bind="control"
                                        class="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="NO_CATEGORY">
                                            No category
                                        </SelectItem>
                                        <SelectItem
                                            v-for="category in categories"
                                            :key="category.value"
                                            :value="category.value"
                                        >
                                            {{ category.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </AppField>
                        </form.Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Cover image</CardTitle>
                        <CardDescription>
                            JPG, PNG or WEBP, up to 4 MB. An upload replaces the
                            stored path.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-5">
                        <img
                            v-if="existingCoverUrl"
                            :src="existingCoverUrl"
                            alt="Current cover image"
                            class="aspect-video w-full rounded-lg border border-border object-cover"
                        />

                        <form.Field name="cover_image" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Upload"
                                #default="{ control }"
                            >
                                <FileDropzone
                                    v-bind="control"
                                    :model-value="field.state.value"
                                    :accept="[
                                        'image/jpeg',
                                        'image/png',
                                        'image/webp',
                                    ]"
                                    :max-size-mb="4"
                                    :max-files="1"
                                    hint="Replaces whatever the post has now."
                                    @update:model-value="field.handleChange"
                                />
                            </AppField>
                        </form.Field>

                        <form.Field
                            name="cover_image_path"
                            #default="{ field }"
                        >
                            <TextField
                                :field="field"
                                label="Stored path"
                                description="The R2 object key. Filled in automatically when the AI step renders a cover."
                                placeholder="posts/ai/….png"
                            />
                        </form.Field>
                    </CardContent>
                </Card>

                <Card v-if="isAiGenerated">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            AI provenance
                            <Badge variant="secondary">Generated</Badge>
                        </CardTitle>
                        <CardDescription>
                            Recorded with the post. Editing the body does not
                            re-score it.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <PostQualityScores
                            compact
                            :seo-score="seoScore"
                            :eeat-score="eeatScore"
                            :human-writing-index="humanWritingIndex"
                            :ai-detection-risk="aiDetectionRisk"
                        />
                    </CardContent>
                </Card>
            </div>
        </div>

        <div
            class="sticky bottom-0 flex items-center justify-end gap-3 border-t border-border bg-background/85 py-4 backdrop-blur"
        >
            <Button variant="ghost" as-child>
                <Link :href="index()">Cancel</Link>
            </Button>

            <Button type="submit" :disabled="isSubmitting">
                <Spinner v-if="isSubmitting" class="size-4" />
                <SaveIcon v-else class="size-4" aria-hidden="true" />
                {{ post ? 'Save changes' : 'Create post' }}
            </Button>
        </div>
    </form>
</template>
