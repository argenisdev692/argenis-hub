<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@lucide/vue';
import { computed } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import type { FilterSelectOption } from '@/common/form';
import { AppField, FilterSelect, TextField } from '@/common/form';
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
    TagsInput,
    TagsInputInput,
    TagsInputItem,
    TagsInputItemDelete,
    TagsInputItemText,
} from '@/components/ui/tags-input';
import SocialMediaInsightsCard from '@/modules/social-media/components/SocialMediaInsightsCard.vue';
import SocialMediaPlatformPreview from '@/modules/social-media/components/SocialMediaPlatformPreview.vue';
import SocialMediaQualityScores from '@/modules/social-media/components/SocialMediaQualityScores.vue';
import SocialMediaStatusBadge from '@/modules/social-media/components/SocialMediaStatusBadge.vue';
import { useSocialMediaContentForm } from '@/modules/social-media/composables/useSocialMediaContentForm';
import {
    brandVoiceLabel,
    businessGoalLabel,
    contentLanguageLabel,
    formatDateTime,
    funnelStageLabel,
} from '@/modules/social-media/helpers/socialMediaPresentation';
import { SOCIAL_MEDIA_EDITABLE_STATUSES } from '@/modules/social-media/schemas/socialMediaContentFormSchema';
import type { SocialMediaContentDetail } from '@/modules/social-media/types';
import { index } from '@/routes/social-media';

/**
 * The human review pass over an AI-generated package.
 *
 * The form is owned here rather than in a child component: `Edit` is its only
 * consumer (there is no create form — content is AI-born), so handing the form
 * down would flatten it to `AnyFormApi` and take every field name's type with
 * it, in exchange for an indirection nothing else uses.
 */
const { content } = defineProps<{ content: SocialMediaContentDetail }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Social media', href: index() }],
    },
});

const form = useSocialMediaContentForm({ content });

const isSubmitting = form.useStore((state) => state.isSubmitting);
const status = form.useStore((state) => state.values.status);

const statusOptions: FilterSelectOption[] = SOCIAL_MEDIA_EDITABLE_STATUSES.map(
    (value) => ({
        value,
        label: {
            draft: 'Draft',
            ready: 'Ready',
            needs_review: 'Needs review',
            published: 'Published',
            scheduled: 'Scheduled',
        }[value],
    }),
);

/** The generation brief, as read-only context for the reviewer's decisions. */
const brief = computed(() =>
    [
        { label: 'Goal', value: businessGoalLabel(content.business_goal) },
        { label: 'Voice', value: brandVoiceLabel(content.brand_voice) },
        { label: 'Funnel', value: funnelStageLabel(content.funnel_stage) },
        { label: 'Language', value: contentLanguageLabel(content.language) },
        { label: 'Provider', value: content.provider },
        { label: 'Niche', value: content.niche ?? '—' },
        { label: 'Audience', value: content.audience ?? '—' },
        { label: 'Angle', value: content.angle ?? '—' },
    ].filter((row) => row.value !== ''),
);

const aiDetectionRiskValue = computed(
    () => content.ai_detection_risk?.value ?? null,
);
</script>

<template>
    <Head :title="content.topic" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <Button as-child variant="ghost" size="sm" class="-ml-2 w-fit">
            <Link :href="index()">
                <ArrowLeftIcon class="size-4" aria-hidden="true" />
                Back to social media
            </Link>
        </Button>

        <header class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ content.topic }}
                </h1>
                <SocialMediaStatusBadge
                    :status="content.status"
                    :deleted-at="content.deleted_at"
                />
            </div>

            <p class="text-sm text-muted-foreground">
                {{ businessGoalLabel(content.business_goal) }} ·
                {{ funnelStageLabel(content.funnel_stage) }}
                <span v-if="content.updated_at">
                    · last edited {{ formatDateTime(content.updated_at) }}</span
                >
            </p>
        </header>

        <form
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
            @submit.prevent="form.handleSubmit()"
        >
            <div class="flex min-w-0 flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Master copy</CardTitle>
                        <CardDescription>
                            The per-platform variants below were adapted from
                            this. Editing it here does not re-adapt them —
                            re-run generation for that.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-5">
                        <form.Field name="headline" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Headline"
                                required
                                placeholder="The hook that stops the scroll"
                            />
                        </form.Field>

                        <form.Field name="body" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Body"
                                required
                                multiline
                                :rows="12"
                            />
                        </form.Field>

                        <form.Field name="call_to_action" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Call to action"
                                required
                                placeholder="Book a free 20-minute audit"
                            />
                        </form.Field>

                        <form.Field name="hashtags" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Hashtags"
                                description="Press Enter after each tag. Up to 20."
                            >
                                <TagsInput
                                    :model-value="field.state.value"
                                    @update:model-value="
                                        (value) =>
                                            field.handleChange(
                                                value as string[],
                                            )
                                    "
                                    @blur="field.handleBlur"
                                >
                                    <TagsInputItem
                                        v-for="tag in field.state.value"
                                        :key="tag"
                                        :value="tag"
                                    >
                                        <TagsInputItemText />
                                        <TagsInputItemDelete />
                                    </TagsInputItem>

                                    <TagsInputInput
                                        placeholder="#growth, #saas…"
                                    />
                                </TagsInput>
                            </AppField>
                        </form.Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Platform variants</CardTitle>
                        <CardDescription>
                            Generated per network, with their own artwork and
                            character budgets.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <SocialMediaPlatformPreview
                            :platforms="content.platforms"
                        />
                    </CardContent>
                </Card>

                <SocialMediaInsightsCard
                    :eeat-analysis="content.eeat_analysis"
                    :optimization-suggestions="content.optimization_suggestions"
                    :research-sources="content.research_sources"
                    :ai-detection-risk="content.ai_detection_risk"
                    :quality-warning-message="content.quality_warning_message"
                    :iterations-required="content.iterations_required"
                />
            </div>

            <aside class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Publishing</CardTitle>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-5">
                        <form.Field name="status" #default="{ field }">
                            <AppField :field="field" label="Status" required>
                                <FilterSelect
                                    :options="statusOptions"
                                    :clearable="false"
                                    :model-value="field.state.value"
                                    @update:model-value="
                                        (value) =>
                                            field.handleChange(
                                                value as typeof field.state.value,
                                            )
                                    "
                                />
                            </AppField>
                        </form.Field>

                        <!--
                            Only meaningful while scheduled, and the backend
                            agrees: `required_if:status,scheduled`.
                        -->
                        <form.Field
                            v-if="status === 'scheduled'"
                            name="scheduled_at"
                            #default="{ field }"
                        >
                            <AppField
                                :field="field"
                                label="Publish at"
                                required
                                description="Your local time. Must be in the future."
                            >
                                <template #default="{ control }">
                                    <Input
                                        v-bind="control"
                                        type="datetime-local"
                                        :model-value="field.state.value"
                                        @update:model-value="
                                            (value) =>
                                                field.handleChange(
                                                    String(value),
                                                )
                                        "
                                        @blur="field.handleBlur"
                                    />
                                </template>
                            </AppField>
                        </form.Field>

                        <PermissionGuard permission="UPDATE_SOCIAL_MEDIA">
                            <Button type="submit" :disabled="isSubmitting">
                                {{ isSubmitting ? 'Saving…' : 'Save changes' }}
                            </Button>
                        </PermissionGuard>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Quality</CardTitle>
                        <CardDescription>
                            The five axes the generation loop optimised against.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <SocialMediaQualityScores
                            compact
                            :human-writing-index="content.human_writing_index"
                            :virality-score="content.virality_score"
                            :engagement-score="content.engagement_score"
                            :roi-score="content.roi_score"
                            :trend-alignment="content.trend_alignment"
                            :ai-detection-risk="aiDetectionRiskValue"
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Brief</CardTitle>
                        <CardDescription>
                            What this package was generated from.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <img
                            v-if="content.cover_image_url"
                            :src="content.cover_image_url"
                            :alt="`Cover artwork for ${content.topic}`"
                            loading="lazy"
                            class="mb-4 w-full rounded-lg border border-border"
                        />

                        <dl class="grid grid-cols-[7rem_1fr] gap-x-3 gap-y-2">
                            <template v-for="row in brief" :key="row.label">
                                <dt class="text-sm text-muted-foreground">
                                    {{ row.label }}
                                </dt>
                                <dd class="text-sm break-words">
                                    {{ row.value }}
                                </dd>
                            </template>
                        </dl>
                    </CardContent>
                </Card>
            </aside>
        </form>
    </div>
</template>
