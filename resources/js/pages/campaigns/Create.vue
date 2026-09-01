<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftIcon, LightbulbIcon, SparklesIcon } from '@lucide/vue';
import { computed, useId } from 'vue';
import EmptyState from '@/common/feedback/EmptyState.vue';
import type { FilterSelectOption } from '@/common/form';
import { AppField, FilterSelect, TextField, useAppForm } from '@/common/form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import CampaignTopicIdeaList from '@/modules/campaigns/components/CampaignTopicIdeaList.vue';
import { useCampaignAi } from '@/modules/campaigns/composables/useCampaignAi';
import {
    CAMPAIGN_AD_FORMAT_OPTIONS,
    CAMPAIGN_BRAND_VOICE_OPTIONS,
    CAMPAIGN_BUSINESS_GOAL_OPTIONS,
    CAMPAIGN_FUNNEL_STAGE_OPTIONS,
    CAMPAIGN_LANGUAGE_OPTIONS,
    CAMPAIGN_PLATFORM_OPTIONS,
} from '@/modules/campaigns/helpers/campaignPresentation';
import {
    campaignBriefSchema,
    emptyCampaignBrief,
    toGenerateCampaignPayload,
    toSuggestTopicsPayload,
} from '@/modules/campaigns/schemas/campaignBriefSchema';
import type { CampaignBriefValues } from '@/modules/campaigns/schemas/campaignBriefSchema';
import type { CampaignTopicIdea } from '@/modules/campaigns/types';
import { edit, index } from '@/routes/campaigns';

/**
 * The two-step generation wizard — the only way a campaign enters this module.
 *
 * ## Why one form for two endpoints
 *
 * Both steps read the same brief: provider, language, goal, niche, audience and
 * the four geo fields. Ideation uses a subset, generation uses all of it. One
 * `useAppForm` over one Zod schema means those shared fields are validated once
 * and stored once — two forms would need either duplicated state or a store to
 * sync them.
 *
 * Ideating does not commit anything: it fills the brief, and the user still has
 * to press Generate. That split is what lets someone audition ten angles for
 * one cheap call and pay for the expensive one once.
 *
 * ## Why the submit is custom
 *
 * `useAppForm`'s Inertia submit expects a redirect-and-flash round trip. This
 * endpoint answers `202` with a row still in `generating`, so the page starts a
 * poll (in `useCampaignAi`) and routes to the review screen only once the job
 * settles.
 */

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Campaigns', href: index() }],
    },
});

const { topicIdeas, generatingUuid, generatingCampaign, suggest, generate } =
    useCampaignAi({
        onReady: (campaign) => {
            router.visit(edit(campaign.uuid).url);
        },
    });

const form = useAppForm({
    defaultValues: emptyCampaignBrief(),
    schema: campaignBriefSchema,
    onSubmit: async (values: CampaignBriefValues) => {
        await generate
            .mutateAsync(toGenerateCampaignPayload(values))
            // `onError` in the composable already toasts; swallowing here keeps
            // a failed call from surfacing as an unhandled rejection.
            .catch(() => undefined);
    },
});

const values = form.useStore((state) => state.values);
const selectedTopic = form.useStore((state) => state.values.topic);

/** True from the moment generation is accepted until the poll settles. */
const isGenerating = computed(() => generatingUuid.value !== null);

const imagesId = useId();

const PROVIDER_OPTIONS: FilterSelectOption[] = [
    { value: 'openai', label: 'OpenAI' },
    { value: 'anthropic', label: 'Anthropic' },
    { value: 'gemini', label: 'Gemini' },
];

/**
 * Ideation runs off the current brief without submitting the form, so it
 * deliberately skips the schema: `topic` is required for generation and is
 * precisely what this step exists to produce. The server re-validates the
 * subset it needs.
 */
async function onSuggestTopics(): Promise<void> {
    await suggest
        .mutateAsync(toSuggestTopicsPayload(values.value))
        .catch(() => undefined);
}

/**
 * Applies a suggestion to the brief.
 *
 * `funnel_stage` is taken from the idea because the agent assigns it to match
 * the angle, and it is what later drives which CTA and Meta objective the
 * generator applies — a TOFU hook generated under a BOFU brief reads wrong.
 * `provider`, `language`, `business_goal`, `brand_voice`, `platform` and
 * `ad_format` are left alone: those are the user's standing media choices, not
 * the idea's.
 */
function onSelectIdea(idea: CampaignTopicIdea): void {
    form.setFieldValue('topic', idea.title);
    form.setFieldValue('angle', idea.angle);
    form.setFieldValue('hook', idea.hook);
    form.setFieldValue('key_trend', idea.key_trend);

    const stage = CAMPAIGN_FUNNEL_STAGE_OPTIONS.find(
        (option) => option.value === idea.funnel_stage,
    );

    if (stage) {
        form.setFieldValue(
            'funnel_stage',
            stage.value as CampaignBriefValues['funnel_stage'],
        );
    }
}
</script>

<template>
    <Head title="Generate campaign" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <Button as-child variant="ghost" size="sm" class="-ml-2 w-fit">
            <Link :href="index()">
                <ArrowLeftIcon class="size-4" aria-hidden="true" />
                Back to campaigns
            </Link>
        </Button>

        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Generate campaign
            </h1>
            <p class="text-sm text-muted-foreground">
                Set the brief, ideate for free, then spend one generation call
                on the angle you want. The quality loop re-runs up to five times
                until all five scores clear their thresholds.
            </p>
        </header>

        <form
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]"
            @submit.prevent="form.handleSubmit()"
        >
            <div class="flex min-w-0 flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>1 · Brief</CardTitle>
                        <CardDescription>
                            Shared by both steps. Leave niche and audience blank
                            to let the agent lean on the company profile and
                            trend research.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <form.Field name="provider" #default="{ field }">
                                <AppField
                                    :field="field"
                                    label="Provider"
                                    required
                                >
                                    <FilterSelect
                                        :options="PROVIDER_OPTIONS"
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

                            <form.Field name="language" #default="{ field }">
                                <AppField
                                    :field="field"
                                    label="Language"
                                    required
                                >
                                    <FilterSelect
                                        :options="CAMPAIGN_LANGUAGE_OPTIONS"
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

                            <form.Field
                                name="business_goal"
                                #default="{ field }"
                            >
                                <AppField
                                    :field="field"
                                    label="Business goal"
                                    required
                                >
                                    <FilterSelect
                                        :options="
                                            CAMPAIGN_BUSINESS_GOAL_OPTIONS
                                        "
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

                            <form.Field name="brand_voice" #default="{ field }">
                                <AppField
                                    :field="field"
                                    label="Brand voice"
                                    required
                                >
                                    <FilterSelect
                                        :options="CAMPAIGN_BRAND_VOICE_OPTIONS"
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

                            <form.Field name="niche" #default="{ field }">
                                <TextField
                                    :field="field"
                                    label="Niche"
                                    placeholder="Residential solar installation"
                                />
                            </form.Field>

                            <form.Field name="audience" #default="{ field }">
                                <TextField
                                    :field="field"
                                    label="Audience"
                                    placeholder="Homeowners aged 35–60 with a mortgage"
                                />
                            </form.Field>
                        </div>

                        <div class="flex flex-col gap-2">
                            <p class="text-sm font-medium">Geography</p>
                            <p class="text-xs text-muted-foreground">
                                Steers the trend research and the Meta targeting
                                suggestions. All optional — use
                                <em>Region</em> for anything the three fields
                                above it cannot express.
                            </p>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <form.Field name="city" #default="{ field }">
                                    <TextField
                                        :field="field"
                                        label="City"
                                        placeholder="Lisbon"
                                    />
                                </form.Field>

                                <form.Field name="state" #default="{ field }">
                                    <TextField
                                        :field="field"
                                        label="State / province"
                                        placeholder="Lisboa"
                                    />
                                </form.Field>

                                <form.Field name="country" #default="{ field }">
                                    <TextField
                                        :field="field"
                                        label="Country"
                                        placeholder="Portugal"
                                    />
                                </form.Field>

                                <form.Field
                                    name="location"
                                    #default="{ field }"
                                >
                                    <TextField
                                        :field="field"
                                        label="Region"
                                        placeholder="Greater Lisbon metro area"
                                    />
                                </form.Field>
                            </div>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            class="w-fit"
                            :disabled="suggest.isLoading.value || isGenerating"
                            @click="onSuggestTopics"
                        >
                            <Spinner
                                v-if="suggest.isLoading.value"
                                class="size-4"
                            />
                            <LightbulbIcon
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />
                            {{
                                suggest.isLoading.value
                                    ? 'Ideating…'
                                    : 'Suggest angles'
                            }}
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>2 · Angle</CardTitle>
                        <CardDescription>
                            Pick a suggestion, or write your own. Angle, hook
                            and trend steer the generator — all optional.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-5">
                        <CampaignTopicIdeaList
                            v-if="topicIdeas.length"
                            :ideas="topicIdeas"
                            :selected-title="selectedTopic"
                            @select="onSelectIdea"
                        />

                        <EmptyState
                            v-else
                            title="No suggestions yet"
                            description="Run the ideation step above, or write a topic below."
                        />

                        <form.Field name="topic" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Topic"
                                required
                                placeholder="Why most solar quotes cost you money"
                            />
                        </form.Field>

                        <form.Field name="angle" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Angle"
                                placeholder="Contrarian take backed by install-cost data"
                            />
                        </form.Field>

                        <form.Field name="hook" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Hook"
                                placeholder="Your last quote was 30% too high — here's how to tell"
                            />
                        </form.Field>

                        <form.Field name="key_trend" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Key trend"
                                placeholder="Grid-price volatility driving self-generation"
                            />
                        </form.Field>
                    </CardContent>
                </Card>
            </div>

            <aside class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>3 · Placement</CardTitle>
                        <CardDescription>
                            Where the ad runs and what it looks like. Artwork is
                            billed per call — turn it off for a copy-only draft.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-5">
                        <form.Field name="platform" #default="{ field }">
                            <AppField :field="field" label="Network" required>
                                <FilterSelect
                                    :options="CAMPAIGN_PLATFORM_OPTIONS"
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

                        <form.Field name="ad_format" #default="{ field }">
                            <AppField :field="field" label="Ad format" required>
                                <FilterSelect
                                    :options="CAMPAIGN_AD_FORMAT_OPTIONS"
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

                        <form.Field name="funnel_stage" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Funnel stage"
                                required
                            >
                                <FilterSelect
                                    :options="CAMPAIGN_FUNNEL_STAGE_OPTIONS"
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

                        <form.Field name="generate_images" #default="{ field }">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    :id="imagesId"
                                    :model-value="field.state.value"
                                    @update:model-value="
                                        (value) =>
                                            field.handleChange(value === true)
                                    "
                                />
                                <Label :for="imagesId">
                                    Generate cover and per-network artwork
                                </Label>
                            </div>
                        </form.Field>

                        <Button
                            type="submit"
                            :disabled="generate.isLoading.value || isGenerating"
                        >
                            <Spinner
                                v-if="generate.isLoading.value || isGenerating"
                                class="size-4"
                            />
                            <SparklesIcon
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />
                            {{
                                isGenerating
                                    ? 'Generating…'
                                    : 'Generate campaign'
                            }}
                        </Button>

                        <p
                            v-if="isGenerating"
                            class="text-sm text-muted-foreground"
                            role="status"
                            aria-live="polite"
                        >
                            The quality loop is running — up to five passes.
                            You'll land on the review screen automatically.
                            <span v-if="generatingCampaign">
                                Current status: {{ generatingCampaign.status }}.
                            </span>
                        </p>
                    </CardContent>
                </Card>
            </aside>
        </form>
    </div>
</template>
