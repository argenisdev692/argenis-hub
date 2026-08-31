<script setup lang="ts">
import {
    FilmIcon,
    LightbulbIcon,
    Share2Icon,
    SparklesIcon,
    WandSparklesIcon,
} from '@lucide/vue';
import { computed, ref, useId, watch } from 'vue';
import EmptyState from '@/common/feedback/EmptyState.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePostAi } from '../composables/usePostAi';
import type {
    GeneratedPostContent,
    PostAiBrief,
    PostAiProvider,
    PostCategoryOption,
    PostImageMode,
    PostReelPackage,
    PostSocialCopy,
    PostTopicIdea,
} from '../types';
import PostAiReelResult from './PostAiReelResult.vue';
import PostAiSocialCopyResult from './PostAiSocialCopyResult.vue';
import PostAiTopicIdeaList from './PostAiTopicIdeaList.vue';
import PostQualityScores from './PostQualityScores.vue';

/**
 * The AI assist surface for the Create/Edit pages.
 *
 * ## Why one container for four endpoints
 *
 * All four calls read from the same brief — provider, topic, angle, key trend.
 * Splitting them into sibling components would mean either duplicating that
 * state or lifting it into a store to share between two components on one
 * page. Holding it here is smaller than both, and it keeps the sequence the
 * feature is actually built around intact: ideate, pick, generate, repurpose.
 *
 * The children below are presentational; this is the only piece that knows an
 * endpoint exists.
 *
 * ## Why nothing here writes to the post
 *
 * Generating is not saving. The draft lands in the form through the `apply`
 * event and the user still has to review and submit it — which is also what
 * makes "generate again" a safe thing to offer, since the previous result was
 * never persisted.
 */

const { categories, defaultCategoryUuid = null } = defineProps<{
    categories: readonly PostCategoryOption[];
    /** Seeds the ideation category from whatever the form already has. */
    defaultCategoryUuid?: string | null;
}>();

const emit = defineEmits<{
    apply: [draft: GeneratedPostContent];
}>();

const {
    suggestPostTopics,
    generatePostContent,
    generatePostSocialCopy,
    generatePostReel,
} = usePostAi();

const PROVIDERS: { value: PostAiProvider; label: string }[] = [
    { value: 'openai', label: 'OpenAI' },
    { value: 'anthropic', label: 'Anthropic' },
    { value: 'gemini', label: 'Gemini' },
];

const IMAGE_MODES: { value: PostImageMode; label: string; hint: string }[] = [
    {
        value: 'full',
        label: 'Full cover',
        hint: 'Background, subject and title',
    },
    {
        value: 'base',
        label: 'Background only',
        hint: 'Palette plate to composite on',
    },
    { value: 'none', label: 'Prompts only', hint: 'No image call is billed' },
];

const brief = ref<PostAiBrief>({
    provider: 'openai',
    category_uuid: defaultCategoryUuid,
    topic: '',
    angle: null,
    key_trend: null,
    image_mode: 'full',
});

// The form owns the category; follow it until the user overrides it here.
watch(
    () => defaultCategoryUuid,
    (value) => {
        if (value && !brief.value.category_uuid) {
            brief.value.category_uuid = value;
        }
    },
);

const ideas = ref<PostTopicIdea[]>([]);
const draft = ref<GeneratedPostContent | null>(null);
const socialCopy = ref<PostSocialCopy | null>(null);
const reel = ref<PostReelPackage | null>(null);
const tab = ref('topics');

const uid = useId();
const topicFieldId = `${uid}-topic`;
const providerFieldId = `${uid}-provider`;
const categoryFieldId = `${uid}-category`;

const canIdeate = computed(() => Boolean(brief.value.category_uuid));
const canGenerate = computed(() => brief.value.topic.trim().length > 0);

function isProvider(value: unknown): value is PostAiProvider {
    return PROVIDERS.some((provider) => provider.value === value);
}

function isImageMode(value: unknown): value is PostImageMode {
    return IMAGE_MODES.some((mode) => mode.value === value);
}

/** The three fields every generator needs, normalized to the wire shape. */
const variantPayload = computed(() => ({
    topic: brief.value.topic.trim(),
    provider: brief.value.provider,
    angle: brief.value.angle,
    key_trend: brief.value.key_trend,
}));

/**
 * Each handler swallows its rejection: `usePostAi` already toasted the failure,
 * and re-throwing here would surface as an unhandled rejection with nothing
 * left to do about it.
 */
async function onSuggestTopics(): Promise<void> {
    if (!brief.value.category_uuid) {
        return;
    }

    try {
        ideas.value = await suggestPostTopics.mutateAsync({
            provider: brief.value.provider,
            category_uuid: brief.value.category_uuid,
            topic: brief.value.topic.trim() || null,
        });
    } catch {
        // Already reported.
    }
}

function onSelectIdea(idea: PostTopicIdea): void {
    brief.value.topic = idea.title;
    brief.value.angle = idea.angle;
    brief.value.key_trend = idea.key_trend;
    tab.value = 'draft';
}

async function onGenerateDraft(): Promise<void> {
    try {
        draft.value = await generatePostContent.mutateAsync({
            ...variantPayload.value,
            image_mode: brief.value.image_mode,
        });

        emit('apply', draft.value);
    } catch {
        // Already reported.
    }
}

async function onGenerateSocialCopy(): Promise<void> {
    try {
        socialCopy.value = await generatePostSocialCopy.mutateAsync(
            variantPayload.value,
        );
    } catch {
        // Already reported.
    }
}

async function onGenerateReel(): Promise<void> {
    try {
        reel.value = await generatePostReel.mutateAsync(variantPayload.value);
    } catch {
        // Already reported.
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <WandSparklesIcon class="size-4" aria-hidden="true" />
                AI assist
            </CardTitle>
            <CardDescription>
                Optional. Write the post by hand and none of this runs — every
                button here is a billed provider call.
            </CardDescription>
        </CardHeader>

        <CardContent class="flex flex-col gap-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <Label :for="providerFieldId">Provider</Label>
                    <Select
                        :model-value="brief.provider"
                        @update:model-value="
                            (value) =>
                                isProvider(value) && (brief.provider = value)
                        "
                    >
                        <SelectTrigger :id="providerFieldId" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="provider in PROVIDERS"
                                :key="provider.value"
                                :value="provider.value"
                            >
                                {{ provider.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label :for="categoryFieldId">Category (the niche)</Label>
                    <Select
                        :model-value="brief.category_uuid ?? undefined"
                        @update:model-value="
                            (value) =>
                                (brief.category_uuid =
                                    typeof value === 'string' ? value : null)
                        "
                    >
                        <SelectTrigger :id="categoryFieldId" class="w-full">
                            <SelectValue placeholder="Pick a category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="category in categories"
                                :key="category.value"
                                :value="category.value"
                            >
                                {{ category.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <Label :for="topicFieldId">Topic</Label>
                <Input
                    :id="topicFieldId"
                    v-model="brief.topic"
                    placeholder="Leave blank to let the category drive ideation"
                    :aria-describedby="`${topicFieldId}-hint`"
                />
                <p
                    :id="`${topicFieldId}-hint`"
                    class="text-xs text-muted-foreground"
                >
                    Required to generate a draft. Picking a suggested idea fills
                    it in, along with its angle and trend.
                </p>
            </div>

            <Tabs v-model="tab">
                <TabsList class="w-full">
                    <TabsTrigger value="topics">
                        <LightbulbIcon class="size-4" aria-hidden="true" />
                        Topics
                    </TabsTrigger>
                    <TabsTrigger value="draft">
                        <SparklesIcon class="size-4" aria-hidden="true" />
                        Draft
                    </TabsTrigger>
                    <TabsTrigger value="social">
                        <Share2Icon class="size-4" aria-hidden="true" />
                        Social
                    </TabsTrigger>
                    <TabsTrigger value="reel">
                        <FilmIcon class="size-4" aria-hidden="true" />
                        Reel
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="topics" class="flex flex-col gap-4 pt-4">
                    <Button
                        type="button"
                        class="self-start"
                        :disabled="
                            !canIdeate || suggestPostTopics.isLoading.value
                        "
                        @click="onSuggestTopics"
                    >
                        <Spinner
                            v-if="suggestPostTopics.isLoading.value"
                            class="size-4"
                        />
                        <LightbulbIcon
                            v-else
                            class="size-4"
                            aria-hidden="true"
                        />
                        Suggest topics
                    </Button>

                    <p v-if="!canIdeate" class="text-xs text-muted-foreground">
                        Pick a category first — it is the niche the research
                        runs against.
                    </p>

                    <PostAiTopicIdeaList
                        v-if="ideas.length"
                        :ideas="ideas"
                        :selected-title="brief.topic || null"
                        @select="onSelectIdea"
                    />
                </TabsContent>

                <TabsContent value="draft" class="flex flex-col gap-4 pt-4">
                    <fieldset class="flex flex-col gap-2">
                        <legend
                            class="mb-2 text-xs font-medium text-muted-foreground"
                        >
                            Cover artwork
                        </legend>

                        <div class="grid gap-2 sm:grid-cols-3">
                            <label
                                v-for="mode in IMAGE_MODES"
                                :key="mode.value"
                                class="flex cursor-pointer items-start gap-2 rounded-lg border border-border p-2.5 text-sm transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                            >
                                <input
                                    type="radio"
                                    name="image-mode"
                                    class="mt-0.5 accent-primary"
                                    :value="mode.value"
                                    :checked="brief.image_mode === mode.value"
                                    @change="
                                        isImageMode(mode.value) &&
                                        (brief.image_mode = mode.value)
                                    "
                                />
                                <span class="flex flex-col">
                                    <span class="font-medium">
                                        {{ mode.label }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ mode.hint }}
                                    </span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <Button
                        type="button"
                        class="self-start"
                        :disabled="
                            !canGenerate || generatePostContent.isLoading.value
                        "
                        @click="onGenerateDraft"
                    >
                        <Spinner
                            v-if="generatePostContent.isLoading.value"
                            class="size-4"
                        />
                        <SparklesIcon
                            v-else
                            class="size-4"
                            aria-hidden="true"
                        />
                        Generate draft
                    </Button>

                    <p
                        v-if="generatePostContent.isLoading.value"
                        class="text-xs text-muted-foreground"
                        aria-live="polite"
                    >
                        Researching, drafting and scoring — this runs up to five
                        passes and can take a minute.
                    </p>

                    <template v-if="draft">
                        <Alert v-if="draft.quality_warning" variant="default">
                            <AlertTitle>Below the quality bar</AlertTitle>
                            <AlertDescription>
                                {{
                                    draft.quality_warning_message ??
                                    'Read this through before publishing.'
                                }}
                            </AlertDescription>
                        </Alert>

                        <PostQualityScores
                            :seo-score="draft.seo_score"
                            :eeat-score="draft.eeat_score"
                            :human-writing-index="draft.human_writing_index"
                            :ai-detection-risk="draft.ai_detection_risk"
                        />

                        <p class="text-xs text-muted-foreground">
                            Applied to the form after
                            {{ draft.iterations_required }}
                            {{
                                draft.iterations_required === 1
                                    ? 'pass'
                                    : 'passes'
                            }}. Review it before saving.
                        </p>

                        <div
                            v-if="draft.optimization_suggestions.length"
                            class="flex flex-col gap-1.5"
                        >
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Suggestions
                            </p>
                            <ul
                                class="list-disc space-y-1 ps-5 text-xs text-muted-foreground"
                            >
                                <li
                                    v-for="suggestion in draft.optimization_suggestions"
                                    :key="suggestion"
                                >
                                    {{ suggestion }}
                                </li>
                            </ul>
                        </div>
                    </template>
                </TabsContent>

                <TabsContent value="social" class="flex flex-col gap-4 pt-4">
                    <Button
                        type="button"
                        class="self-start"
                        :disabled="
                            !canGenerate ||
                            generatePostSocialCopy.isLoading.value
                        "
                        @click="onGenerateSocialCopy"
                    >
                        <Spinner
                            v-if="generatePostSocialCopy.isLoading.value"
                            class="size-4"
                        />
                        <Share2Icon v-else class="size-4" aria-hidden="true" />
                        Generate social copy
                    </Button>

                    <PostAiSocialCopyResult
                        v-if="socialCopy"
                        :copy="socialCopy"
                    />
                    <EmptyState
                        v-else
                        title="No social copy yet"
                        description="Generates a LinkedIn post, a short caption and hashtags from the topic above."
                    />
                </TabsContent>

                <TabsContent value="reel" class="flex flex-col gap-4 pt-4">
                    <Button
                        type="button"
                        class="self-start"
                        :disabled="
                            !canGenerate || generatePostReel.isLoading.value
                        "
                        @click="onGenerateReel"
                    >
                        <Spinner
                            v-if="generatePostReel.isLoading.value"
                            class="size-4"
                        />
                        <FilmIcon v-else class="size-4" aria-hidden="true" />
                        Generate reel package
                    </Button>

                    <PostAiReelResult v-if="reel" :reel="reel" />
                    <EmptyState
                        v-else
                        title="No reel package yet"
                        description="Generates a scene-by-scene shot list, script, sound suggestion and TikTok caption."
                    />
                </TabsContent>
            </Tabs>
        </CardContent>
    </Card>
</template>
