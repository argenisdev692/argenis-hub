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
import SocialMediaTopicIdeaList from '@/modules/social-media/components/SocialMediaTopicIdeaList.vue';
import { useSocialMediaAi } from '@/modules/social-media/composables/useSocialMediaAi';
import {
    BRAND_VOICE_OPTIONS,
    BUSINESS_GOAL_OPTIONS,
    FUNNEL_STAGE_OPTIONS,
    LANGUAGE_OPTIONS,
} from '@/modules/social-media/helpers/socialMediaPresentation';
import {
    emptySocialMediaBrief,
    socialMediaBriefSchema,
    toGenerateContentPayload,
    toSuggestTopicsPayload,
} from '@/modules/social-media/schemas/socialMediaBriefSchema';
import type { SocialMediaBriefValues } from '@/modules/social-media/schemas/socialMediaBriefSchema';
import type { SocialMediaTopicIdea } from '@/modules/social-media/types';
import { edit, index } from '@/routes/social-media';

/**
 * The two-step generation wizard — the only way content enters this module.
 *
 * ## Why one form for two endpoints
 *
 * Both steps read the same brief: provider, language, goal, niche, audience.
 * Ideation uses a subset, generation uses all of it. One `useAppForm` over one
 * Zod schema means those shared fields are validated once and stored once —
 * two forms would need either duplicated state or a store to sync them.
 *
 * Ideating does not commit anything: it fills the brief, and the user still
 * has to press Generate. That split is what lets someone audition ten topics
 * for one cheap call and pay for the expensive one once.
 *
 * ## Why the submit is custom
 *
 * `useAppForm`'s Inertia submit expects a redirect-and-flash round trip. This
 * endpoint answers `202` with a row still in `generating`, so the page starts a
 * poll (in `useSocialMediaAi`) and routes to the review screen only once the
 * job settles.
 */

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Social media', href: index() }],
    },
});

const { topicIdeas, generatingUuid, generatingContent, suggest, generate } =
    useSocialMediaAi({
        onReady: (content) => {
            router.visit(edit(content.uuid).url);
        },
    });

const form = useAppForm({
    defaultValues: emptySocialMediaBrief(),
    schema: socialMediaBriefSchema,
    onSubmit: async (values: SocialMediaBriefValues) => {
        await generate
            .mutateAsync(toGenerateContentPayload(values))
            // `onError` in the composable already toasts; swallowing here keeps
            // a failed call from surfacing as an unhandled rejection.
            .catch(() => undefined);
    },
});

const values = form.useStore((state) => state.values);
const selectedTopic = form.useStore((state) => state.values.topic);

/** True from the moment generation is accepted until the poll settles. */
const isGenerating = computed(() => generatingUuid.value !== null);

const voiceoverId = useId();

const PROVIDER_OPTIONS: FilterSelectOption[] = [
    { value: 'openai', label: 'OpenAI' },
    { value: 'anthropic', label: 'Anthropic' },
    { value: 'gemini', label: 'Gemini' },
];

const IMAGE_MODE_OPTIONS: FilterSelectOption[] = [
    { value: 'full', label: 'Full — cover + per-platform artwork' },
    { value: 'base', label: 'Base — palette plates only' },
    { value: 'none', label: 'None — prompts only, no image call' },
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
 * `funnel_stage` is taken from the idea because the agent picks it to match the
 * topic, and a TOFU hook generated under a BOFU brief reads wrong. `provider`,
 * `language`, `business_goal` and `brand_voice` are left alone — those are the
 * user's standing choices, not the idea's.
 */
function onSelectIdea(idea: SocialMediaTopicIdea): void {
    form.setFieldValue('topic', idea.title);
    form.setFieldValue('angle', idea.angle);
    form.setFieldValue('hook', idea.hook);
    form.setFieldValue('key_trend', idea.key_trend);

    const stage = FUNNEL_STAGE_OPTIONS.find(
        (option) => option.value === idea.funnel_stage,
    );

    if (stage) {
        form.setFieldValue(
            'funnel_stage',
            stage.value as SocialMediaBriefValues['funnel_stage'],
        );
    }
}
</script>

<template>
    <Head title="Generate content" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
        <Button as-child variant="ghost" size="sm" class="-ml-2 w-fit">
            <Link :href="index()">
                <ArrowLeftIcon class="size-4" aria-hidden="true" />
                Back to social media
            </Link>
        </Button>

        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Generate content
            </h1>
            <p class="text-sm text-muted-foreground">
                Set the brief, ideate for free, then spend one generation call
                on the topic you want. The quality loop re-runs up to five times
                until every score clears its threshold.
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
                                        :options="LANGUAGE_OPTIONS"
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
                                        :options="BUSINESS_GOAL_OPTIONS"
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
                                        :options="BRAND_VOICE_OPTIONS"
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
                                    placeholder="B2B SaaS onboarding"
                                />
                            </form.Field>

                            <form.Field name="audience" #default="{ field }">
                                <TextField
                                    :field="field"
                                    label="Audience"
                                    placeholder="Heads of growth at 20–200 person startups"
                                />
                            </form.Field>
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
                                    : 'Suggest topics'
                            }}
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>2 · Topic</CardTitle>
                        <CardDescription>
                            Pick a suggestion, or write your own. Angle, hook
                            and trend steer the generator — all optional.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-5">
                        <SocialMediaTopicIdeaList
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
                                placeholder="Why most onboarding emails go unread"
                            />
                        </form.Field>

                        <form.Field name="angle" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Angle"
                                placeholder="Contrarian take backed by activation data"
                            />
                        </form.Field>

                        <form.Field name="hook" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Hook"
                                placeholder="Your welcome email is a 4% open rate away from irrelevance"
                            />
                        </form.Field>

                        <form.Field name="key_trend" #default="{ field }">
                            <TextField
                                :field="field"
                                label="Key trend"
                                placeholder="Lifecycle messaging consolidation"
                            />
                        </form.Field>
                    </CardContent>
                </Card>
            </div>

            <aside class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>3 · Output</CardTitle>
                        <CardDescription>
                            Artwork and audio are billed per call — the cheaper
                            modes are here for drafts.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-5">
                        <form.Field name="funnel_stage" #default="{ field }">
                            <AppField
                                :field="field"
                                label="Funnel stage"
                                required
                            >
                                <FilterSelect
                                    :options="FUNNEL_STAGE_OPTIONS"
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

                        <form.Field name="image_mode" #default="{ field }">
                            <AppField :field="field" label="Artwork" required>
                                <FilterSelect
                                    :options="IMAGE_MODE_OPTIONS"
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
                            name="generate_voiceover"
                            #default="{ field }"
                        >
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    :id="voiceoverId"
                                    :model-value="field.state.value"
                                    @update:model-value="
                                        (value) =>
                                            field.handleChange(value === true)
                                    "
                                />
                                <Label :for="voiceoverId">
                                    Generate voiceover audio
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
                                    : 'Generate content'
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
                            <span v-if="generatingContent">
                                Current status: {{ generatingContent.status }}.
                            </span>
                        </p>
                    </CardContent>
                </Card>
            </aside>
        </form>
    </div>
</template>
