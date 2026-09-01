/**
 * Posts module types.
 *
 * Aliases over the generated declarations wherever the backend actually ships a
 * `Data` class. `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from those classes, so those names track
 * the backend on their own; writing the same shape out by hand is how a renamed
 * field turns into a runtime `undefined` instead of a build error.
 *
 * The list and detail rows below are the exception, and deliberately so: the
 * Post module has no read-model DTO for the admin surface — `ListPostsHandler`
 * returns a paginator of `PostEloquentModel` and the controller hands it
 * straight to `response()->json()`. What arrives is therefore the Eloquent
 * serialization (the repository's `select()`, minus `$hidden`, plus
 * `$appends`), which no generated type describes. Each block below names the
 * exact source line it mirrors so the two can be re-checked together.
 */

/** The lifecycle values `PostStatus` defines, as they arrive over the wire. */
export type PostStatus = Modules.Post.Domain.Enums.PostStatus;

/** How much cover artwork the AI assist step should render. */
export type PostImageMode = Modules.Post.Domain.Enums.PostImageMode;

/** The providers `PostData::rules()` and the AI DTOs both whitelist. */
export type PostAiProvider = 'openai' | 'anthropic' | 'gemini';

/**
 * The blog category as it rides along on a post row —
 * `with('category:id,uuid,blog_category_name')`, minus the model's
 * `$hidden = ['id']`.
 */
export type PostCategoryRef = {
    uuid: string;
    blog_category_name: string;
};

/** `with('user:id,first_name,last_name')`. Null once an author is deleted. */
export type PostAuthorRef = {
    first_name: string | null;
    last_name: string | null;
};

/**
 * One row of the admin list — exactly the columns
 * `EloquentPostRepository::paginate()` selects.
 *
 * `post_content`, the meta fields and the remaining AI scores are absent by
 * design: the list does not render them, and a `select()` that pulls a full
 * article body per row to show a title is the cheapest N+1 there is.
 */
export type PostListItem = {
    uuid: string;
    post_title: string;
    post_title_slug: string;
    post_excerpt: string | null;
    post_cover_image: string | null;
    /** Resolved through `StoragePort`; null when the R2 lookup fails. */
    cover_image_url: string | null;
    post_status: PostStatus;
    scheduled_at: string | null;
    published_at: string | null;
    is_ai_generated: boolean;
    seo_score: number | null;
    eeat_score: number | null;
    human_writing_index: number | null;
    created_at: string;
    deleted_at: string | null;
    category: PostCategoryRef | null;
    user: PostAuthorRef | null;
};

/**
 * A single post as `GET /posts/{uuid}/edit` shares it —
 * `EloquentPostRepository::findByUuid()`, which selects every column.
 */
export type PostDetail = PostListItem & {
    post_content: string;
    meta_title: string | null;
    meta_description: string | null;
    meta_keywords: string | null;
    ai_provider: string | null;
    ai_generated_at: string | null;
    ai_detection_risk: number | null;
    ai_scores: Record<string, unknown> | null;
    updated_at: string;
};

/**
 * One page of the admin list.
 *
 * Deliberately NOT the generated `Illuminate.LengthAwarePaginator<TKey,
 * TValue>`: that stub models Inertia's `{ data, links, meta: {...} }`
 * prop-transformation shape, but `PostController::index()` answers an XHR with
 * `response()->json($posts)`, which serializes Laravel's own flat
 * `LengthAwarePaginator::toArray()` — `current_page`, `from`, `last_page`,
 * `per_page`, `to` and `total` sit at the top level, not nested under `meta`.
 * Same shape as `PortfolioPage` and `ServicePage`.
 */
export type PostPage = {
    data: PostListItem[];
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
 * one axis, because that is how `PostFilterData` reads it: `draft`, `published`
 * and `scheduled` match `post_status`, while `suspended` switches the query to
 * `onlyTrashed()`. One `<select>`, not two.
 */
export type PostStatusFilter = 'all' | PostStatus | 'suspended';

/** `PostFilterData::SORTABLE`. Anything else is coerced to `created_at`. */
export type PostSortField =
    'created_at' | 'post_title' | 'post_status' | 'published_at' | 'seo_score';

/**
 * The query params `GET /posts` accepts when asked for JSON.
 *
 * Snake_case throughout: `PostFilterData` carries
 * `#[MapInputName(SnakeCaseMapper::class)]`, so this is the wire shape, not the
 * camelCase property names PHP declares.
 */
export type PostFilters = {
    search: string;
    status: PostStatusFilter;
    category_uuid: string | null;
    /** Inclusive `created_at` lower bound, `YYYY-MM-DD`. */
    date_from: string | null;
    /** Inclusive `created_at` upper bound, `YYYY-MM-DD`. */
    date_to: string | null;
    sort_field: PostSortField;
    sort_order: 1 | -1;
    page: number;
    per_page: number;
};

/**
 * The body `POST /posts` and `PUT /posts/{uuid}` accept — mirrors `PostData`,
 * whose `MapInputName(SnakeCaseMapper::class)` makes these the wire names.
 *
 * `cover_image` (a fresh upload) and `cover_image_path` (an R2 key the AI step
 * already produced) are mutually exclusive; the handler prefers the upload when
 * both arrive. The `ai_*` fields are write-once provenance echoed back from
 * `GeneratePostContentHandler`, never typed by a user — a hand-written post
 * simply leaves them at their defaults.
 */
export type PostWritePayload = {
    title: string;
    content: string;
    excerpt: string | null;
    cover_image: File | null;
    cover_image_path: string | null;
    meta_title: string | null;
    meta_description: string | null;
    meta_keywords: string | null;
    category_uuid: string | null;
    status: PostStatus;
    scheduled_at: string | null;
    is_ai_generated: boolean;
    ai_provider: string | null;
    seo_score: number | null;
    eeat_score: number | null;
    human_writing_index: number | null;
    ai_detection_risk: number | null;
    ai_scores: Record<string, unknown> | null;
};

/** `{ value, label }` pairs from `PostController::categoryOptions()`. */
export type PostCategoryOption = {
    value: string;
    label: string;
};

// ---- AI assist -------------------------------------------------------------

/** Request body for `POST /posts/ai/suggest-topics`. */
export type SuggestTopicsPayload = {
    provider: PostAiProvider;
    category_uuid: string;
    topic: string | null;
};

/** Request body for `POST /posts/ai/generate-content`. */
export type GenerateContentPayload = {
    topic: string;
    provider: PostAiProvider;
    angle: string | null;
    key_trend: string | null;
    image_mode: PostImageMode;
};

/**
 * Request body for `POST /posts/ai/generate-social-copy` and
 * `POST /posts/ai/generate-reel` — `GenerateContentVariantData` backs both.
 */
export type GenerateVariantPayload = {
    topic: string;
    provider: PostAiProvider;
    angle: string | null;
    key_trend: string | null;
};

/**
 * What the user settles on before spending a generation call — the shared
 * state behind the assist panel's three generators, so picking a topic once
 * feeds the draft, the social copy and the reel package alike.
 */
export type PostAiBrief = {
    provider: PostAiProvider;
    category_uuid: string | null;
    topic: string;
    angle: string | null;
    key_trend: string | null;
    image_mode: PostImageMode;
};

export type PostTopicIdea = Modules.Post.Application.DTOs.PostTopicIdeaData;
export type GeneratedPostContent =
    Modules.Post.Application.DTOs.GeneratedPostContentData;

/** One background draft run, as returned by accept and by every poll. */
export type PostAiGeneration =
    Modules.Post.Application.DTOs.PostAiGenerationData;
export type PostAiGenerationStatus =
    Modules.Post.Domain.Enums.PostAiGenerationStatus;

/**
 * The phases the progress list renders, in the order the pipeline runs them.
 *
 * `draft` / `queued` are not here: nothing has started yet, so every row shows
 * as pending, which is exactly right. `completed` / `failed` are terminal and
 * end the list rather than appearing in it.
 */
export const POST_GENERATION_PHASES = [
    'researching',
    'writing',
    'judging',
    'generating_image',
] as const satisfies readonly PostAiGenerationStatus[];

export type PostGenerationPhase = (typeof POST_GENERATION_PHASES)[number];
export type PostSocialCopy = Modules.Post.Application.DTOs.SocialCopyData;
export type PostReelPackage = Modules.Post.Application.DTOs.ReelPackageData;
export type PostReelScene = Modules.Post.Application.DTOs.ReelSceneData;

/** Every AI endpoint answers `{ data: … }`. */
export type AiEnvelope<TData> = { data: TData };
