/**
 * Campaigns module types.
 *
 * Aliases over the generated declarations wherever the backend actually ships a
 * `Data` class. `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from those classes, so those names track
 * the backend on their own; writing the same shape out by hand is how a renamed
 * field turns into a runtime `undefined` instead of a build error.
 *
 * The list and detail rows below are the exception, and deliberately so — the
 * same situation as `modules/social-media/types.ts`: the module has no
 * read-model DTO for the admin surface. `ListCampaignsHandler` returns a
 * paginator of `CampaignEloquentModel` and `CampaignController::index()` hands
 * it straight to `response()->json()`. What arrives is therefore the Eloquent
 * serialization (the repository's `select()`, minus `$hidden = ['id',
 * 'created_by']`, plus `$appends = ['cover_image_url']`), which no generated
 * type describes. Each block names the exact source line it mirrors so the two
 * can be re-checked together.
 *
 * ## On the request payloads
 *
 * `SuggestCampaignTopicsData` and `GenerateCampaignData` both carry
 * `#[MapInputName(SnakeCaseMapper::class)]`, so the wire names are snake_case.
 * The generated file shows them camelCase because the transformer emits OUTPUT
 * names, and neither DTO is ever serialized outward. Sending `businessGoal`
 * would validate as a missing `business_goal`, so the request shapes are
 * spelled out here in the casing the server actually reads.
 */

/** The lifecycle values `CampaignStatus` defines, as they arrive over the wire. */
export type CampaignStatus = Modules.Campaigns.Domain.Enums.CampaignStatus;

export type CampaignBusinessGoal =
    Modules.Campaigns.Domain.Enums.CampaignBusinessGoal;
export type CampaignBrandVoice =
    Modules.Campaigns.Domain.Enums.CampaignBrandVoice;
export type CampaignFunnelStage =
    Modules.Campaigns.Domain.Enums.CampaignFunnelStage;
export type CampaignPlatform = Modules.Campaigns.Domain.Enums.CampaignPlatform;
export type CampaignAdFormat = Modules.Campaigns.Domain.Enums.CampaignAdFormat;
export type CampaignLanguage = Modules.Campaigns.Domain.Enums.CampaignLanguage;

/** The providers every AI DTO in this module whitelists via `Rule::in`. */
export type CampaignAiProvider = 'openai' | 'anthropic' | 'gemini';

/**
 * `with('creator:id,first_name,last_name')`.
 *
 * `id` is present, unlike the Post module's author ref: the `User` model does
 * not hide it, and `creator` is loaded by the repository rather than projected
 * through a DTO. Null once the creating user's row is gone.
 */
export type CampaignCreatorRef = {
    id: number;
    first_name: string | null;
    last_name: string | null;
};

/**
 * One row of the admin list — exactly the columns
 * `EloquentCampaignRepository::paginate()` selects, minus `id` and
 * `created_by` (the model's `$hidden`), plus the appended `cover_image_url`.
 *
 * The copy, the per-platform packages and the individual score breakdowns are
 * absent by design: the table renders none of them, and selecting a full
 * generated package per row to show a topic is the cheapest N+1 there is.
 */
export type CampaignListItem = {
    uuid: string;
    topic: string;
    status: CampaignStatus;
    business_goal: CampaignBusinessGoal;
    funnel_stage: CampaignFunnelStage;
    platform: CampaignPlatform;
    ad_format: CampaignAdFormat;
    language: CampaignLanguage;
    provider: string;
    /** Mean of the five quality scores; null until the first evaluation runs. */
    overall_score_avg: number | null;
    /** The evaluator's own wording — `high`, `medium`, … — not a computed band. */
    success_probability_label: string | null;
    all_scores_pass: boolean;
    /** True when the quality loop exhausted its iterations below threshold. */
    quality_warning: boolean;
    scheduled_at: string | null;
    published_at: string | null;
    created_at: string;
    deleted_at: string | null;
    /** Resolved through `StoragePort`; null when the R2 lookup fails. */
    cover_image_url: string | null;
    creator: CampaignCreatorRef | null;
};

/**
 * The `platforms` blob, keyed by platform name. Mirrors
 * `PlatformCampaignContentData`, which is what `GeneratedCampaignData` stores
 * into the column.
 */
export type CampaignPlatformContent =
    Modules.Campaigns.Application.DTOs.PlatformCampaignContentData;

/** The five-axis quality breakdown stored in the `scores` column. */
export type CampaignScoreSet =
    Modules.Campaigns.Application.DTOs.CampaignScoreSetData;

/**
 * `ai_detection_risk` — an `array` cast, so it arrives as an object rather than
 * the bare number the Post module stores.
 */
export type CampaignAiDetectionRisk = {
    value: number;
    label: string;
    explanation: string;
};

/** One entry of the `research_sources` array. */
export type CampaignResearchSource = {
    source: string;
    relevance: string;
    key_insight: string;
    used_in: string[];
};

/**
 * A single campaign as `GET /campaigns/{uuid}/edit` shares it —
 * `EloquentCampaignRepository::findByUuid()`, which selects every column (and
 * is `withTrashed()`, so a suspended campaign still renders).
 *
 * `platforms` is typed as a keyed record because that is what a generated
 * campaign carries. PHP serializes the empty default as `[]` rather than `{}`,
 * which `Object.entries()` reads as "no variants" either way — see
 * `CampaignPlatformPreview`.
 */
export type CampaignDetail = CampaignListItem & {
    niche: string | null;
    angle: string | null;
    hook: string | null;
    key_trend: string | null;
    audience: string | null;
    brand_voice: CampaignBrandVoice;
    headline: string | null;
    primary_text: string | null;
    description: string | null;
    call_to_action: string | null;
    hashtags: string[] | null;
    lead_form_questions: string[] | null;
    targeting_suggestions: string[] | null;
    platforms: Record<string, CampaignPlatformContent> | null;
    cover_image_path: string | null;
    cover_image_prompt: string | null;
    scores: CampaignScoreSet | null;
    audience_fit_score: number | null;
    virality_score: number | null;
    roi_potential_score: number | null;
    lead_quality_score: number | null;
    trend_relevance_score: number | null;
    iterations_required: number | null;
    quality_warning_message: string | null;
    optimization_suggestions: string[] | null;
    research_sources: CampaignResearchSource[] | null;
    tavily_data_used: string[] | null;
    ai_detection_risk: CampaignAiDetectionRisk | null;
    updated_at: string;
};

/**
 * One page of the admin list.
 *
 * Deliberately NOT the generated `Illuminate.LengthAwarePaginator<TKey,
 * TValue>`: that stub models Inertia's `{ data, links, meta: {...} }`
 * prop-transformation shape, but `CampaignController::index()` answers an XHR
 * with `response()->json($campaigns)`, which serializes Laravel's own flat
 * `LengthAwarePaginator::toArray()` — `current_page`, `from`, `last_page`,
 * `per_page`, `to` and `total` sit at the top level, not nested under `meta`.
 * Same shape as `PostPage` and `SocialMediaContentPage`.
 */
export type CampaignPage = {
    data: CampaignListItem[];
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
 * The status filter folds the campaign lifecycle and the soft-delete state onto
 * one axis, because that is how the backend reads it: `CampaignFilterData`
 * whitelists the six lifecycle values, while `suspended` is the one the
 * repository turns into `onlyTrashed()`. One `<select>`, not two.
 */
export type CampaignStatusFilter = 'all' | CampaignStatus | 'suspended';

/**
 * The query params `GET /campaigns` accepts when asked for JSON.
 *
 * Snake_case throughout: `CampaignFilterData` extends `SoftDeleteFilterData`,
 * which carries `#[MapName(SnakeCaseMapper::class)]`.
 *
 * There is no sort axis — `paginate()` hard-codes `orderByDesc('created_at')`,
 * so offering the user a sortable column would be a control that does nothing.
 * `per_page` is read straight off the request by the controller (clamped to
 * 1..100), not by the filter DTO.
 */
export type CampaignFilters = {
    search: string;
    status: CampaignStatusFilter;
    /** Inclusive `created_at` lower bound, `YYYY-MM-DD`. */
    date_from: string | null;
    /** Inclusive `created_at` upper bound, `YYYY-MM-DD`. */
    date_to: string | null;
    page: number;
    per_page: number;
};

// ---- AI assist -------------------------------------------------------------

/**
 * Request body for `POST /campaigns/ai/suggest-topics` — the snake_case wire
 * form of `SuggestCampaignTopicsData`.
 */
export type SuggestCampaignTopicsPayload = {
    provider: CampaignAiProvider;
    language: CampaignLanguage;
    niche: string | null;
    audience: string | null;
    business_goal: CampaignBusinessGoal | null;
    city: string | null;
    state: string | null;
    country: string | null;
    location: string | null;
};

/**
 * Request body for `POST /campaigns/ai/generate-campaign` — the snake_case wire
 * form of `GenerateCampaignData`.
 */
export type GenerateCampaignPayload = {
    topic: string;
    provider: CampaignAiProvider;
    language: CampaignLanguage;
    business_goal: CampaignBusinessGoal;
    brand_voice: CampaignBrandVoice;
    funnel_stage: CampaignFunnelStage;
    platform: CampaignPlatform;
    ad_format: CampaignAdFormat;
    angle: string | null;
    hook: string | null;
    key_trend: string | null;
    niche: string | null;
    audience: string | null;
    generate_images: boolean;
    city: string | null;
    state: string | null;
    country: string | null;
    location: string | null;
};

/** One of the exactly-10 candidate angles from Step 1 of the wizard. */
export type CampaignTopicIdea =
    Modules.Campaigns.Application.DTOs.CampaignTopicIdeaData;

/** Every AI endpoint in this module answers `{ data: … }`. */
export type AiEnvelope<TData> = { data: TData };
