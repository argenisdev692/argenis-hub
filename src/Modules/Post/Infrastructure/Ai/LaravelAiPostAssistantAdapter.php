<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Post\Application\Commands\GeneratePostContentHandler;
use Modules\Post\Application\DTOs\GenerateContentVariantData;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostContentDraftData;
use Modules\Post\Application\DTOs\PostTopicIdeaData;
use Modules\Post\Application\DTOs\ReelPackageData;
use Modules\Post\Application\DTOs\ReelSceneData;
use Modules\Post\Application\DTOs\SocialCopyData;
use Modules\Post\Application\DTOs\SuggestPostTopicsData;
use Modules\Post\Domain\Ports\PostContentEvaluatorPort;
use Modules\Post\Domain\Ports\PostContentGeneratorPort;
use Modules\Post\Domain\Ports\PostCoverImageRendererPort;
use Modules\Post\Domain\Ports\PostTopicIdeatorPort;
use Modules\Post\Domain\Ports\ReelPackageGeneratorPort;
use Modules\Post\Domain\Ports\SocialCopyGeneratorPort;
use Modules\Post\Domain\Services\PostContentQualityEvaluator;
use Modules\Post\Infrastructure\Broadcasting\PostProgressNotifier;
use Shared\Domain\Ports\SpeechSynthesizerPort;
use Shared\Domain\Ports\StoragePort;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\Company\CompanyProfile;
use Shared\Infrastructure\Research\TavilyClientInterface;

/**
 * Single adapter behind the text-producing Post AI ports — they share the same
 * underlying `laravel/ai` + Tavily infrastructure, so one class composing the
 * agents avoids duplicating the research/prompt-assembly plumbing while each
 * port stays small (ISP) for its own consumer.
 *
 * It performs exactly ONE attempt per {@see self::generate()} call and returns
 * text only. The other two thirds of an attempt live elsewhere on purpose:
 * scoring in {@see PostContentEvaluatorPort} (a different model, so the gate
 * is independent) and artwork in {@see PostCoverImageRendererPort} (once,
 * after the loop, so rejected drafts cost no images). The loop itself is
 * {@see GeneratePostContentHandler}.
 *
 * Caching lives HERE, in the module adapter — never in the Shared AI/Tavily
 * clients (those stay pure transport + circuit breaker). `suggestTopics` /
 * social / reel cache the full result. `generate()` caches ONLY the first
 * attempt (iteration 1, no previous weaknesses) — from iteration 2 onward the
 * quality loop deliberately targets specific failing scores, so those attempts
 * are never safe to reuse from cache.
 */
final readonly class LaravelAiPostAssistantAdapter implements PostContentGeneratorPort, PostTopicIdeatorPort, ReelPackageGeneratorPort, SocialCopyGeneratorPort
{
    private const int CACHE_TTL_MINUTES = 15;

    public function __construct(
        private AIClientInterface $ai,
        private TavilyClientInterface $research,
        private StoragePort $storage,
        private SpeechSynthesizerPort $speech,
        private PostProgressNotifier $progress,
    ) {}

    public function suggestTopics(SuggestPostTopicsData $data, ?object $causer = null): array
    {
        return Cache::remember(
            $this->cacheKey('suggest-topics', $data->toArray()),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($data, $causer): array {
                $this->progress->notify($causer, 'topics', 'researching', 'Researching current trends…', 20);

                $company = CompanyProfile::data();
                $category = $this->resolveCategory($data->categoryUuid);

                // Category-first flow: the chosen category IS the niche. A
                // freehand `topic` only narrows the angle inside it, so both are
                // folded into the research phrase when present.
                $niche = trim($category['name'].' '.($data->topic ?? ''));

                $research = $this->research->search([
                    "{$niche} trends 2026",
                    "{$niche} viral content ideas",
                    "{$niche} audience pain points",
                ]);

                $prompt = $this->buildTopicsPrompt($data, $category, $company, $research);

                $this->progress->notify($causer, 'topics', 'generating', 'Drafting topic ideas…', 60);

                $response = $this->ai->generateStructured(SuggestPostTopicsAgent::class, $prompt, $data->provider);

                $ideas = array_map(
                    static fn (array $idea): PostTopicIdeaData => new PostTopicIdeaData(
                        title: (string) $idea['title'],
                        angle: (string) $idea['angle'],
                        hook: (string) $idea['hook'],
                        estimatedVirality: (int) $idea['estimated_virality'],
                        estimatedRoi: (int) $idea['estimated_roi'],
                        eeatPotential: (int) $idea['eeat_potential'],
                        whyItWorks: (string) $idea['why_it_works'],
                        keyTrend: (string) $idea['key_trend'],
                    ),
                    (array) $response['content_ideas'],
                );

                $this->progress->notify($causer, 'topics', 'done', 'Topic ideas ready.', 100);

                return $ideas;
            },
        );
    }

    public function generate(
        GeneratePostContentData $data,
        int $iteration = 1,
        array $previousWeaknesses = [],
        ?object $causer = null,
    ): PostContentDraftData {
        $attempt = fn (): PostContentDraftData => $this->generateAttempt($data, $iteration, $previousWeaknesses, $causer);

        if ($iteration !== 1 || $previousWeaknesses !== []) {
            return $attempt();
        }

        return Cache::remember(
            // `image_mode` is excluded: it steers the renderer, not a single
            // word of the text this cache holds. Keying on it split one
            // reusable draft into three identical entries and re-ran the whole
            // quality loop when the user merely toggled the cover option.
            $this->cacheKey('generate', $this->payloadExcept($data->toArray(), ['image_mode'])),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            $attempt,
        );
    }

    /**
     * Drops fields that must not take part in a cache key.
     *
     * Keys are normalised to snake_case first because `Data::toArray()` emits
     * PROPERTY names: {@see GeneratePostContentData} carries `MapInputName`
     * only, so the key is `imageMode`, and a naive `array_diff_key` against
     * `image_mode` silently excluded nothing at all.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $except
     * @return array<string, mixed>
     */
    private function payloadExcept(array $payload, array $except): array
    {
        $normalized = [];

        foreach ($payload as $key => $value) {
            $normalized[Str::snake((string) $key)] = $value;
        }

        return array_diff_key($normalized, array_flip($except));
    }

    /**
     * @param  list<array{score: string, current: int, target: int, gap: int, explanation: string}>  $previousWeaknesses
     */
    private function generateAttempt(
        GeneratePostContentData $data,
        int $iteration,
        array $previousWeaknesses,
        ?object $causer,
    ): PostContentDraftData {
        $progressBase = (int) round((($iteration - 1) / PostContentQualityEvaluator::MAX_ITERATIONS) * 80);

        $this->progress->notify($causer, 'content', 'researching', "Iteration {$iteration}: researching…", $progressBase + 5);

        $company = CompanyProfile::data();
        $research = $this->research->search(
            $this->researchQueriesForIteration($data->topic, $data->keyTrend, $iteration),
        );
        $prompt = $this->buildContentPrompt($data, $company, $research, $iteration, $previousWeaknesses);

        $this->progress->notify($causer, 'content', 'writing', "Iteration {$iteration}: writing the blog draft…", $progressBase + 8);

        $response = $this->ai->generateStructured(GeneratePostContentAgent::class, $prompt, $data->provider);

        /** @var array{title: string, visual: string} $concept */
        $concept = (array) $response['cover_image_concept'];
        /** @var array{primary_keyword: string, lsi_keywords: list<string>} $seoAnalysis */
        $seoAnalysis = (array) $response['seo_analysis'];

        return new PostContentDraftData(
            title: (string) $response['title'],
            content: (string) $response['content'],
            excerpt: (string) $response['excerpt'],
            metaTitle: (string) $response['meta_title'],
            metaDescription: (string) $response['meta_description'],
            metaKeywords: (string) $response['meta_keywords'],
            coverImageConcept: ['title' => (string) $concept['title'], 'visual' => (string) $concept['visual']],
            seoAnalysis: $seoAnalysis,
            provider: $data->provider,
        );
    }

    public function generateSocialCopy(GenerateContentVariantData $data, ?object $causer = null): SocialCopyData
    {
        return Cache::remember(
            $this->cacheKey('generate-social-copy', $data->toArray()),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($data, $causer): SocialCopyData {
                $this->progress->notify($causer, 'social', 'researching', 'Researching current trends…', 25);

                $company = CompanyProfile::data();
                $research = $this->research->search($this->researchQueries($data->topic, $data->keyTrend));
                $prompt = $this->buildVariantPrompt(
                    $data,
                    $company,
                    $research,
                    'Write the LinkedIn post and the Instagram/Facebook caption exactly as specified in your instructions.',
                );

                $this->progress->notify($causer, 'social', 'writing', 'Writing the social copy…', 70);

                $response = $this->ai->generateStructured(GenerateSocialCopyAgent::class, $prompt, $data->provider);

                $this->progress->notify($causer, 'social', 'done', 'Social copy ready.', 100);

                return new SocialCopyData(
                    linkedinPost: (string) $response['linkedin_post'],
                    socialCaption: (string) $response['social_caption'],
                    hashtags: (array) $response['hashtags'],
                );
            },
        );
    }

    public function generateReelPackage(GenerateContentVariantData $data, ?object $causer = null): ReelPackageData
    {
        return Cache::remember(
            $this->cacheKey('generate-reel-package', $data->toArray()),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($data, $causer): ReelPackageData {
                $this->progress->notify($causer, 'reel', 'researching', 'Researching current trends…', 15);

                $company = CompanyProfile::data();
                $research = $this->research->search($this->researchQueries($data->topic, $data->keyTrend));
                $prompt = $this->buildVariantPrompt(
                    $data,
                    $company,
                    $research,
                    'Write the complete Reel/TikTok package exactly as specified in your instructions.',
                );

                $this->progress->notify($causer, 'reel', 'writing', 'Writing the Reel/TikTok script…', 45);

                $response = $this->ai->generateStructured(GenerateReelPackageAgent::class, $prompt, $data->provider);

                $scenes = array_map(
                    static fn (array $scene): ReelSceneData => new ReelSceneData(
                        timeRange: (string) $scene['time_range'],
                        action: (string) $scene['action'],
                        onScreenText: (string) $scene['on_screen_text'],
                        voiceoverLine: (string) $scene['voiceover_line'],
                        visualPrompt: (string) $scene['visual_prompt'],
                    ),
                    (array) $response['scenes'],
                );

                $cleanScript = (string) $response['clean_script'];

                $this->progress->notify($causer, 'reel', 'voiceover', 'Synthesizing the AI voiceover…', 80);

                // One script, one TTS call: the Reel flow has no quality loop,
                // so this is already the "render once, on the final text" shape
                // the content loop had to be restructured into.
                $voiceoverAudioUrl = $this->generateAndStoreVoiceover($cleanScript);

                $this->progress->notify($causer, 'reel', 'done', 'Reel package ready.', 100);

                $targetDuration = (int) ($response['target_duration_seconds'] ?? 15);
                $targetDuration = max(15, min(30, $targetDuration));

                return new ReelPackageData(
                    scenes: $scenes,
                    cleanScript: $cleanScript,
                    soundSuggestion: (string) $response['sound_suggestion'],
                    tiktokCaption: (string) $response['tiktok_caption'],
                    tiktokHashtags: (array) $response['tiktok_hashtags'],
                    voiceoverAudioUrl: $voiceoverAudioUrl,
                    targetDurationSeconds: $targetDuration,
                    creativeStyle: (string) ($response['creative_style'] ?? 'ugc_native') ?: 'ugc_native',
                );
            },
        );
    }

    /**
     * Deterministic cache key for one AI operation, scoped by every input
     * field that affects the output. `$causer` is deliberately excluded —
     * two different users requesting the identical payload should share the
     * cache entry.
     *
     * @param  array<string, mixed>  $payload
     */
    private function cacheKey(string $operation, array $payload): string
    {
        return 'post:ai:'.$operation.':'.md5(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string>
     */
    private function researchQueries(string $topic, ?string $keyTrend): array
    {
        return array_values(array_filter([
            "{$topic} 2026",
            $keyTrend !== null ? "{$keyTrend} statistics recent data" : null,
            "{$topic} case study results",
        ]));
    }

    /**
     * Fresh Tavily angles per quality-loop iteration (mirrors the POSTS prompt).
     *
     * @return list<string>
     */
    private function researchQueriesForIteration(string $topic, ?string $keyTrend, int $iteration): array
    {
        $base = $this->researchQueries($topic, $keyTrend);
        $trend = $keyTrend ?? $topic;

        return match ($iteration) {
            2 => [...$base, "{$topic} case study results ROI", "{$topic} viral examples social media"],
            3 => [...$base, "{$topic} expert opinion thought leadership", "{$trend} industry report 2026"],
            4 => [...$base, "{$topic} authoritative sources citations", "{$topic} SEO keywords search volume"],
            5 => [...$base, "{$topic} top performing posts engagement", "{$topic} conversion rate benchmarks"],
            default => $base,
        };
    }

    /**
     * Resolves the selected blog category to its name + description, which
     * together define the niche for ideation. Validation already guarantees the
     * UUID exists (`SuggestPostTopicsData::rules()`); the null-coalesce only
     * covers the race where it is soft-deleted between validation and here.
     *
     * @return array{name: string, description: ?string}
     */
    private function resolveCategory(string $categoryUuid): array
    {
        $category = BlogCategoryEloquentModel::query()
            ->select(['blog_category_name', 'blog_category_description'])
            ->where('uuid', $categoryUuid)
            ->first();

        return [
            'name' => (string) ($category?->blog_category_name ?? ''),
            'description' => $category?->blog_category_description,
        ];
    }

    /**
     * @param  array{name: string, description: ?string}  $category
     * @param  array{name: string, description: ?string}  $company
     * @param  list<array{title: string, url: string, content: string, score: float}>  $research
     */
    private function buildTopicsPrompt(SuggestPostTopicsData $data, array $category, array $company, array $research): string
    {
        return implode("\n\n", array_filter([
            "Company: {$company['name']}",
            $company['description'] !== null ? "Company description: {$company['description']}" : null,
            "Content category (the niche): {$category['name']}",
            $category['description'] !== null ? "Category description: {$category['description']}" : null,
            $data->topic !== null
                ? "Narrow the ideas to this angle inside the category: {$data->topic}"
                : 'No extra steer given — spread the ideas across the whole category.',
            'Web research context:'."\n".$this->formatResearch($research),
            "Generate exactly 10 viral blog topic ideas for the \"{$category['name']}\" category, as specified in your instructions.",
        ]));
    }

    /**
     * @param  array{name: string, description: ?string}  $company
     * @param  list<array{title: string, url: string, content: string, score: float}>  $research
     * @param  list<array{score: string, current: int, target: int, gap: int, explanation: string}>  $previousWeaknesses
     */
    private function buildContentPrompt(
        GeneratePostContentData $data,
        array $company,
        array $research,
        int $iteration,
        array $previousWeaknesses,
    ): string {
        return implode("\n\n", array_filter([
            "Company: {$company['name']}",
            $company['description'] !== null ? "Company description: {$company['description']}" : null,
            "Topic: {$data->topic}",
            $data->angle !== null ? "Angle: {$data->angle}" : null,
            $data->keyTrend !== null ? "Key trend to reference: {$data->keyTrend}" : null,
            'Web research context:'."\n".$this->formatResearch($research),
            $this->formatIterationFeedback($iteration, $previousWeaknesses),
            'Write the complete blog post exactly as specified in your instructions.',
        ]));
    }

    /**
     * @param  list<array{score: string, current: int, target: int, gap: int, explanation: string}>  $weaknesses
     */
    private function formatIterationFeedback(int $iteration, array $weaknesses): string
    {
        if ($iteration === 1 || $weaknesses === []) {
            return "Current iteration: {$iteration} of ".PostContentQualityEvaluator::MAX_ITERATIONS
                .'. First attempt — all scores must meet their thresholds.';
        }

        $lines = array_map(
            static fn (array $w): string => "- {$w['score']}: was {$w['current']}, needs {$w['target']}+. Why it failed: {$w['explanation']}",
            $weaknesses,
        );

        return "Iteration {$iteration} of ".PostContentQualityEvaluator::MAX_ITERATIONS
            .". An independent reviewer failed these scores on your previous attempt:\n"
            .implode("\n", $lines)
            ."\nDo NOT repeat the same content — change the hook, evidence, or CTA for each failing score while keeping what worked.";
    }

    /**
     * @param  array{name: string, description: ?string}  $company
     * @param  list<array{title: string, url: string, content: string, score: float}>  $research
     */
    private function buildVariantPrompt(
        GenerateContentVariantData $data,
        array $company,
        array $research,
        string $instruction,
    ): string {
        return implode("\n\n", array_filter([
            "Company: {$company['name']}",
            $company['description'] !== null ? "Company description: {$company['description']}" : null,
            "Topic: {$data->topic}",
            $data->angle !== null ? "Angle: {$data->angle}" : null,
            $data->keyTrend !== null ? "Key trend to reference: {$data->keyTrend}" : null,
            'Web research context:'."\n".$this->formatResearch($research),
            $instruction,
        ]));
    }

    /**
     * @param  list<array{title: string, url: string, content: string, score: float}>  $research
     */
    private function formatResearch(array $research): string
    {
        if ($research === []) {
            return 'No fresh research available — rely on your general knowledge and say so honestly where relevant.';
        }

        return implode("\n", array_map(
            static fn (array $r): string => "- {$r['title']} ({$r['url']}): {$r['content']}",
            array_slice($research, 0, 10),
        ));
    }

    /**
     * Best-effort voiceover for the Reel's clean script. Null on any failure
     * (ElevenLabs unreachable/misconfigured) — the script/timeline remain
     * fully usable without audio.
     */
    private function generateAndStoreVoiceover(string $cleanScript): ?string
    {
        $audio = $this->speech->synthesize($cleanScript);

        if ($audio === null) {
            return null;
        }

        $path = 'posts/ai/audio/'.Str::uuid7().'.mp3';
        $stored = $this->storage->put($path, base64_decode($audio['base64'], true) ?: '', 'public');

        return $this->storage->publicUrl($stored);
    }
}
