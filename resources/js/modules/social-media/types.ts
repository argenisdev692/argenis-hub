/**
 * Social media module types.
 *
 * Aliases over the generated declarations wherever the backend actually ships a
 * `Data` class. `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from those classes, so those names track
 * the backend on their own; writing the same shape out by hand is how a renamed
 * field turns into a runtime `undefined` instead of a build error.
 *
 * The list and detail rows below are the exception, and deliberately so — the
 * same situation as `modules/posts/types.ts`: the module has no read-model DTO
 * for the admin surface. `ListSocialMediaContentHandler` returns a paginator of
 * `SocialMediaContentEloquentModel` and the controller hands it straight to
 * `response()->json()`. What arrives is therefore the Eloquent serialization
 * (the repository's `select()`, minus `$hidden = ['id', 'created_by']`, plus
 * `$appends = ['cover_image_url']`), which no generated type describes. Each
 * block names the exact source line it mirrors so the two can be re-checked
 * together.
 *
 * ## On the request payloads
 *
 * `SuggestSocialMediaTopicsData` and `GenerateSocialMediaContentData` both
 * carry `#[MapInputName(SnakeCaseMapper::class)]`, so the wire names are
 * snake_case. The generated file shows them camelCase because the transformer
 * emits OUTPUT names, and neither DTO is ever serialized outward. Sending
 * `businessGoal` would validate as a missing `business_goal`, so the request
 * shapes are spelled out here in the casing the server actually reads.
 */

/** `SocialMediaContentStatus`, as it arrives over the wire. */
export type SocialMediaContentStatus =
    Modules.SocialMedia.Domain.Enums.SocialMediaContentStatus;

export type BusinessGoal = Modules.SocialMedia.Domain.Enums.BusinessGoal;
export type BrandVoice = Modules.SocialMedia.Domain.Enums.BrandVoice;
export type FunnelStage = Modules.SocialMedia.Domain.Enums.FunnelStage;
export type ContentLanguage = Modules.SocialMedia.Domain.Enums.ContentLanguage;
export type SocialMediaImageMode =
    Modules.SocialMedia.Domain.Enums.SocialMediaImageMode;

/** The providers every AI DTO in this module whitelists via `Rule::in`. */
export type SocialMediaAiProvider = 'openai' | 'anthropic' | 'gemini';

/** `with('user:id,first_name,last_name')`, minus the User model's `$hidden`. */
export type SocialMediaAuthorRef = {
    first_name: string | null;
    last_name: string | null;
};

/**
 * One row of the admin list — exactly the columns
 * `EloquentSocialMediaContentRepository::paginate()` selects, minus `id` and
 * `created_by` (the model's `$hidden`), plus the appended `cover_image_url`.
 *
 * The body, the per-platform packages and the individual score breakdowns are
 * absent by design: the table renders none of them, and selecting a full
 * generated package per row to show a topic is the cheapest N+1 there is.
 */
export type SocialMediaContentListItem = {
    uuid: string;
    topic: string;
    status: SocialMediaContentStatus;
    business_goal: BusinessGoal;
    funnel_stage: FunnelStage;
    language: ContentLanguage;
    provider: string;
    /** Mean of the five quality scores; null until the first evaluation runs. */
    overall_score_avg: number | null;
    all_scores_pass: boolean;
    /** True when the quality loop exhausted its iterations below threshold. */
    quality_warning: boolean;
    cover_image_path: string | null;
    /** Resolved through `StoragePort`; null when the R2 lookup fails. */
    cover_image_url: string | null;
    scheduled_at: string | null;
    published_at: string | null;
    created_at: string;
    deleted_at: string | null;
    user: SocialMediaAuthorRef | null;
};

/**
 * The `platforms` blob, keyed by platform name. Mirrors
 * `PlatformContentData`, which is what `GeneratedSocialMediaContentData`
 * stores into the column.
 */
export type SocialMediaPlatformContent =
    Modules.SocialMedia.Application.DTOs.PlatformContentData;

/** The five-axis quality breakdown stored in the `scores` column. */
export type SocialMediaScoreSet =
    Modules.SocialMedia.Application.DTOs.ScoreSetData;

/**
 * `ai_detection_risk` — an `array` cast, so it arrives as an object rather
 * than the bare number the Post module stores.
 */
export type SocialMediaAiDetectionRisk = {
    value: number;
    label: string;
    explanation: string;
};

/** One entry of the `research_sources` array. */
export type SocialMediaResearchSource = {
    source: string;
    relevance: string;
    key_insight: string;
    used_in: string[];
};

/**
 * A single package as `GET /social-media/{uuid}/edit` shares it —
 * `EloquentSocialMediaContentRepository::findByUuid()`, which selects every
 * column (and is `withTrashed()`, so a suspended package still renders).
 */
export type SocialMediaContentDetail = SocialMediaContentListItem & {
    niche: string | null;
    angle: string | null;
    hook: string | null;
    key_trend: string | null;
    audience: string | null;
    brand_voice: BrandVoice;
    headline: string | null;
    body: string | null;
    call_to_action: string | null;
    hashtags: string[] | null;
    platforms: Record<string, SocialMediaPlatformContent> | null;
    cover_image_prompt: string | null;
    scores: SocialMediaScoreSet | null;
    human_writing_index: number | null;
    virality_score: number | null;
    engagement_score: number | null;
    roi_score: number | null;
    trend_alignment: number | null;
    iterations_required: number | null;
    quality_warning_message: string | null;
    eeat_analysis: Record<string, string[]> | null;
    optimization_suggestions: string[] | null;
    research_sources: SocialMediaResearchSource[] | null;
    tavily_data_used: string[] | null;
    ai_detection_risk: SocialMediaAiDetectionRisk | null;
    updated_at: string;
};

/**
 * One page of the admin list.
 *
 * Deliberately NOT the generated `Illuminate.LengthAwarePaginator<TKey,
 * TValue>`: that stub models Inertia's `{ data, links, meta: {...} }`
 * prop-transformation shape, but `SocialMediaContentController::index()`
 * answers an XHR with `response()->json($content)`, which serializes Laravel's
 * own flat `LengthAwarePaginator::toArray()` — `current_page`, `from`,
 * `last_page`, `per_page`, `to` and `total` sit at the top level, not nested
 * under `meta`. Same shape as `PostPage` and `ServicePage`.
 */
export type SocialMediaContentPage = {
    data: SocialMediaContentListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    links: { url: string | null; label: string; active: boolean }[];
};

/**
 * The status filter folds the content lifecycle and the soft-delete state onto
 * one axis, because that is how the backend reads it:
 * `SocialMediaContentFilterData` whitelists the six lifecycle values, while
 * `suspended` is the one the repository turns into `onlyTrashed()`. One
 * `<select>`, not two.
 */
export type SocialMediaStatusFilter =
    'all' | SocialMediaContentStatus | 'suspended';

/**
 * The query params `GET /social-media` accepts when asked for JSON.
 *
 * Snake_case throughout: `SocialMediaContentFilterData` extends
 * `SoftDeleteFilterData`, which carries `#[MapName(SnakeCaseMapper::class)]`.
 *
 * There is no sort axis — `paginate()` hard-codes `orderByDesc('created_at')`,
 * so offering the user a sortable column would be a control that does nothing.
 * `per_page` is read straight off the request by the controller (clamped to
 * 1..100), not by the filter DTO.
 */
export type SocialMediaContentFilters = {
    search: string;
    status: SocialMediaStatusFilter;
    /** Inclusive `created_at` lower bound, `YYYY-MM-DD`. */
    date_from: string | null;
    /** Inclusive `created_at` upper bound, `YYYY-MM-DD`. */
    date_to: string | null;
    page: number;
    per_page: number;
};

// ---- AI assist -------------------------------------------------------------

/**
 * Request body for `POST /social-media/ai/suggest-topics` — the snake_case
 * wire form of `SuggestSocialMediaTopicsData`.
 */
export type SuggestSocialMediaTopicsPayload = {
    provider: SocialMediaAiProvider;
    language: ContentLanguage;
    niche: string | null;
    audience: string | null;
    business_goal: BusinessGoal | null;
};

/**
 * Request body for `POST /social-media/ai/generate-content` — the snake_case
 * wire form of `GenerateSocialMediaContentData`.
 */
export type GenerateSocialMediaContentPayload = {
    topic: string;
    provider: SocialMediaAiProvider;
    language: ContentLanguage;
    business_goal: BusinessGoal;
    brand_voice: BrandVoice;
    funnel_stage: FunnelStage;
    angle: string | null;
    hook: string | null;
    key_trend: string | null;
    niche: string | null;
    audience: string | null;
    image_mode: SocialMediaImageMode;
    generate_voiceover: boolean;
};

/** One suggestion from Step 1 of the wizard. */
export type SocialMediaTopicIdea =
    Modules.SocialMedia.Application.DTOs.SocialMediaTopicIdeaData;

/** Every AI endpoint in this module answers `{ data: … }`. */
export type AiEnvelope<TData> = { data: TData };
