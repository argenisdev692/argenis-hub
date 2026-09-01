<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, FileTextIcon } from '@lucide/vue';
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
import CampaignInsightsCard from '@/modules/campaigns/components/CampaignInsightsCard.vue';
import CampaignPlatformPreview from '@/modules/campaigns/components/CampaignPlatformPreview.vue';
import CampaignQualityScores from '@/modules/campaigns/components/CampaignQualityScores.vue';
import CampaignStatusBadge from '@/modules/campaigns/components/CampaignStatusBadge.vue';
import { useCampaignForm } from '@/modules/campaigns/composables/useCampaignForm';
import {
    campaignAdFormatLabel,
    campaignBrandVoiceLabel,
    campaignBusinessGoalLabel,
    campaignFunnelStageLabel,
    campaignLanguageLabel,
    campaignPlatformLabel,
    formatDateTime,
    successProbabilityLabel,
} from '@/modules/campaigns/helpers/campaignPresentation';
import { CAMPAIGN_EDITABLE_STATUSES } from '@/modules/campaigns/schemas/campaignFormSchema';
import type { CampaignDetail } from '@/modules/campaigns/types';
import { index, report } from '@/routes/campaigns';

/**
 * The human review pass over an AI-generated campaign.
 *
 * The form is owned here rather than in a child component: `Edit` is its only
 * consumer (there is no create form — a campaign is AI-born), so handing the
 * form down would flatten it to `AnyFormApi` and take every field name's type
 * with it, in exchange for an indirection nothing else uses.
 */
const { campaign } = defineProps<{ campaign: CampaignDetail }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Campaigns', href: index() }],
    },
});

const form = useCampaignForm({ campaign });

const isSubmitting = form.useStore((state) => state.isSubmitting);
const status = form.useStore((state) => state.values.status);

const statusOptions: FilterSelectOption[] = CAMPAIGN_EDITABLE_STATUSES.map(
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
        {
            label: 'Goal',
            value: campaignBusinessGoalLabel(campaign.business_goal),
        },
        {
            label: 'Voice',
            value: campaignBrandVoiceLabel(campaign.brand_voice),
        },
        {
            label: 'Funnel',
            value: campaignFunnelStageLabel(campaign.funnel_stage),
        },
        { label: 'Network', value: campaignPlatformLabel(campaign.platform) },
        { label: 'Format', value: campaignAdFormatLabel(campaign.ad_format) },
        { label: 'Language', value: campaignLanguageLabel(campaign.language) },
        { label: 'Provider', value: campaign.provider },
        { label: 'Niche', value: campaign.niche ?? '—' },
        { label: 'Audience', value: campaign.audience ?? '—' },
        { label: 'Angle', value: campaign.angle ?? '—' },
    ].filter((row) => row.value !== ''),
);

const aiDetectionRiskValue = computed(
    () => campaign.ai_detection_risk?.value ?? null,
);

/**
 * The per-campaign PDF scorecard — rendered fresh on every request, so it
 * always reflects the copy as last saved. A plain `<a>` rather than an Inertia
 * `Link`: the route streams an attachment, not an Inertia response.
 */
const reportUrl = computed(() => report.url(campaign.uuid));
</script>

<template>
    <Head :title="campaign.topic" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <Button as-child variant="ghost" size="sm" class="-ml-2 w-fit">
            <Link :href="index()">
                <ArrowLeftIcon class="size-4" aria-hidden="true" />
                Back to campaigns
            </Link>
        </Button>

        <header class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ campaign.topic }}
                </h1>
                <CampaignStatusBadge
                    :status="campaign.status"
                    :deleted-at="campaign.deleted_at"
                />
            </div>

            <p class="text-sm text-muted-foreground">
                {{ campaignBusinessGoalLabel(campaign.business_goal) }} ·
                {{ campaignFunnelStageLabel(campaign.funnel_stage) }} ·
                {{ campaignPlatformLabel(campaign.platform) }}
                <span v-if="campaign.updated_at">
                    · last edited {{ formatDateTime(campaign.updated_at) }}
                </span>
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
                            The per-network variants below were adapted from
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

                        <form.Field name="primary_text" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Primary text"
                                required
                                multiline
                                :rows="10"
                            />
                        </form.Field>

                        <form.Field name="description" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Description"
                                description="The short line under the headline. Optional, 500 characters max."
                                multiline
                                :rows="3"
                            />
                        </form.Field>

                        <form.Field name="call_to_action" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Call to action"
                                required
                                description="The Meta CTA button token, e.g. GET_QUOTE or LEARN_MORE."
                                placeholder="GET_QUOTE"
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
                                        placeholder="#solar, #leads…"
                                    />
                                </TagsInput>
                            </AppField>
                        </form.Field>

                        <form.Field
                            name="lead_form_questions"
                            #default="{ field }"
                        >
                            <AppField
                                :field="field"
                                label="Lead form questions"
                                description="What the instant form asks. Press Enter after each one. Up to 10."
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
                                        v-for="question in field.state.value"
                                        :key="question"
                                        :value="question"
                                    >
                                        <TagsInputItemText />
                                        <TagsInputItemDelete />
                                    </TagsInputItem>

                                    <TagsInputInput
                                        placeholder="What is your project budget range?"
                                    />
                                </TagsInput>
                            </AppField>
                        </form.Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Network variants</CardTitle>
                        <CardDescription>
                            Generated per placement, with their own artwork and
                            character budgets.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <CampaignPlatformPreview
                            :platforms="campaign.platforms"
                        />
                    </CardContent>
                </Card>

                <CampaignInsightsCard
                    :optimization-suggestions="
                        campaign.optimization_suggestions
                    "
                    :targeting-suggestions="campaign.targeting_suggestions"
                    :research-sources="campaign.research_sources"
                    :tavily-data-used="campaign.tavily_data_used"
                    :ai-detection-risk="campaign.ai_detection_risk"
                    :quality-warning-message="campaign.quality_warning_message"
                    :iterations-required="campaign.iterations_required"
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

                        <PermissionGuard permission="UPDATE_CAMPAIGNS">
                            <Button type="submit" :disabled="isSubmitting">
                                {{ isSubmitting ? 'Saving…' : 'Save changes' }}
                            </Button>
                        </PermissionGuard>

                        <PermissionGuard permission="VIEW_CAMPAIGNS">
                            <Button as-child variant="outline">
                                <a :href="reportUrl">
                                    <FileTextIcon
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Download report
                                </a>
                            </Button>
                        </PermissionGuard>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Quality</CardTitle>
                        <CardDescription>
                            The five axes the generation loop optimised against.
                            <span v-if="campaign.success_probability_label">
                                Success probability:
                                {{
                                    successProbabilityLabel(
                                        campaign.success_probability_label,
                                    )
                                }}.
                            </span>
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <CampaignQualityScores
                            compact
                            :audience-fit-score="campaign.audience_fit_score"
                            :virality-score="campaign.virality_score"
                            :roi-potential-score="campaign.roi_potential_score"
                            :lead-quality-score="campaign.lead_quality_score"
                            :trend-relevance-score="
                                campaign.trend_relevance_score
                            "
                            :ai-detection-risk="aiDetectionRiskValue"
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Brief</CardTitle>
                        <CardDescription>
                            What this campaign was generated from.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <img
                            v-if="campaign.cover_image_url"
                            :src="campaign.cover_image_url"
                            :alt="`Cover artwork for ${campaign.topic}`"
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
