<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Post\Infrastructure\Ai\LaravelAiPostAssistantAdapter;
use Modules\SocialMedia\Application\DTOs\GeneratedSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\ImageConceptData;
use Modules\SocialMedia\Application\DTOs\PlatformContentData;
use Modules\SocialMedia\Application\DTOs\SocialMediaTopicIdeaData;
use Modules\SocialMedia\Application\DTOs\SuggestSocialMediaTopicsData;
use Modules\SocialMedia\Application\DTOs\VideoPackageData;
use Modules\SocialMedia\Application\DTOs\VideoSceneData;
use Modules\SocialMedia\Domain\Enums\ContentLanguage;
use Modules\SocialMedia\Domain\Ports\SocialMediaAssetRendererPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentEvaluatorPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentGeneratorPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaTopicIdeatorPort;
use Modules\SocialMedia\Domain\Services\ContentQualityEvaluator;
use Modules\SocialMedia\Infrastructure\Broadcasting\SocialMediaProgressNotifier;
use Modules\SocialMedia\Infrastructure\Queue\GenerateSocialMediaContentJob;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\Company\CompanyProfile;
use Shared\Infrastructure\Research\TavilyClientInterface;

/**
 * The WRITING adapter behind both text-producing SocialMedia AI ports —
 * mirrors {@see LaravelAiPostAssistantAdapter}: one class composing the agents
 * so the research/prompt-assembly plumbing is not duplicated, while each port
 * stays small (ISP) for its own consumer.
 *
 * It performs exactly ONE attempt per {@see self::generate()} call and returns
 * text only. The other two thirds of an attempt live elsewhere on purpose:
 * scoring in {@see SocialMediaContentEvaluatorPort} (a different model, so the
 * gate is independent) and artwork in {@see SocialMediaAssetRendererPort}
 * (once, after the loop, so rejected drafts cost no images). The loop itself
 * is {@see GenerateSocialMediaContentJob}.
 *
 * Caching lives HERE, in the module adapter — never in the Shared AI/Tavily
 * clients (those stay pure transport + circuit breaker). `suggestTopics()`
 * always caches (no internal state). `generate()` caches ONLY the first
 * attempt (iteration 1, no previous weaknesses) — from iteration 2 onward the
 * quality loop deliberately targets specific failing scores, so those attempts
 * are never safe to reuse from cache.
 */
final readonly class LaravelAiSocialMediaAssistantAdapter implements SocialMediaContentGeneratorPort, SocialMediaTopicIdeatorPort
{
    private const int CACHE_TTL_MINUTES = 15;

    public function __construct(
        private AIClientInterface $ai,
        private TavilyClientInterface $research,
        private SocialMediaProgressNotifier $progress,
    ) {}

    public function suggestTopics(SuggestSocialMediaTopicsData $data, ?object $causer = null): array
    {
        return Cache::remember(
            $this->cacheKey('suggest-topics', $data->toArray()),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($data): array {
                $company = CompanyProfile::data();
                $niche = $data->niche ?? $company['description'] ?? $company['name'];

                $research = $this->research->search([
                    "{$niche} trends 2026",
                    "{$niche} viral content",
                    "{$niche} audience pain points",
                ]);

                $prompt = implode("\n\n", array_filter([
                    "Niche: {$niche}",
                    $data->audience !== null ? "Target audience: {$data->audience}" : 'Target audience: infer from the niche.',
                    $data->businessGoal !== null ? "Business goal: {$data->businessGoal}" : null,
                    ContentLanguage::tryFrom($data->language)?->outputInstruction()
                        ?? "Output language: {$data->language}",
                    'Web research context:'."\n".$this->formatResearch($research),
                    'Generate exactly 10 viral topics as specified in your instructions.',
                ]));

                $response = $this->ai->generateStructured(SuggestSocialMediaTopicsAgent::class, $prompt, $data->provider);

                return array_map(
                    static fn (array $topic): SocialMediaTopicIdeaData => new SocialMediaTopicIdeaData(
                        title: (string) $topic['title'],
                        angle: (string) $topic['angle'],
                        hook: (string) $topic['hook'],
                        platform: (string) $topic['platform'],
                        estimatedVirality: (int) $topic['estimated_virality'],
                        estimatedEngagement: (string) $topic['estimated_engagement'],
                        estimatedRoi: (int) $topic['estimated_roi'],
                        difficulty: (string) $topic['difficulty'],
                        whyItWorks: (string) $topic['why_it_works'],
                        keyTrend: (string) $topic['key_trend'],
                        suggestedFormat: (string) $topic['suggested_format'],
                        contentType: (string) $topic['content_type'],
                        funnelStage: (string) $topic['funnel_stage'],
                    ),
                    (array) $response['viral_topics'],
                );
            },
        );
    }

    public function generate(
        string $contentUuid,
        GenerateSocialMediaContentData $data,
        int $iteration = 1,
        array $previousWeaknesses = [],
        ?object $causer = null,
    ): GeneratedSocialMediaContentData {
        $attempt = fn (): GeneratedSocialMediaContentData => $this->generateAttempt($contentUuid, $data, $iteration, $previousWeaknesses, $causer);

        if ($iteration !== 1 || $previousWeaknesses !== []) {
            return $attempt();
        }

        return Cache::remember(
            // `image_mode` / `generate_voiceover` are excluded: they steer the
            // renderer, not a single word of the text this cache holds. Keying
            // on them split one reusable draft into six identical entries.
            $this->cacheKey('generate', $this->payloadExcept($data->toArray(), ['image_mode', 'generate_voiceover'])),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            $attempt,
        );
    }

    /**
     * @param  list<array{score: string, current: int, target: int, gap: int, explanation: string}>  $previousWeaknesses
     */
    private function generateAttempt(
        string $contentUuid,
        GenerateSocialMediaContentData $data,
        int $iteration,
        array $previousWeaknesses,
        ?object $causer,
    ): GeneratedSocialMediaContentData {
        $this->progress->notify($causer, $contentUuid, 'researching', "Iteration {$iteration}: researching fresh context…", 15, $iteration);

        $company = CompanyProfile::data();
        $research = $this->research->search($this->researchQueries($data, $iteration));
        $prompt = $this->buildContentPrompt($data, $company, $research, $iteration, $previousWeaknesses);

        $this->progress->notify($causer, $contentUuid, 'writing', "Iteration {$iteration}: writing the 5-platform package…", 45, $iteration);

        $response = $this->ai->generateStructured(GenerateSocialMediaContentAgent::class, $prompt, $data->provider);

        $platforms = [];

        foreach ((array) $response['platforms'] as $platform => $variation) {
            /** @var array<string, mixed> $variation */
            $platforms[(string) $platform] = $this->buildPlatformContent((string) $platform, $variation);
        }

        /** @var array{headline: string, body: string, call_to_action: string, hashtags: list<string>} $content */
        $content = (array) $response['content'];

        return new GeneratedSocialMediaContentData(
            headline: (string) $content['headline'],
            body: (string) $content['body'],
            callToAction: (string) $content['call_to_action'],
            hashtags: (array) $content['hashtags'],
            platforms: $platforms,
            coverImageConcept: ImageConceptData::fromResponse((array) $response['cover_image_concept']),
            researchSources: (array) $response['research_sources'],
            tavilyDataUsed: (array) $response['tavily_data_used'],
            provider: $data->provider,
        );
    }

    /**
     * @param  array<string, mixed>  $variation
     */
    private function buildPlatformContent(string $platform, array $variation): PlatformContentData
    {
        return new PlatformContentData(
            platform: $platform,
            adaptedContent: (string) $variation['adapted_content'],
            characterCount: (int) $variation['character_count'],
            hashtags: (array) $variation['hashtags'],
            imageConcept: ImageConceptData::fromResponse((array) $variation['image_concept']),
            isThread: (bool) ($variation['is_thread'] ?? false),
            threadTweets: (array) ($variation['thread_tweets'] ?? []),
            videoPackage: $this->mapVideoPackage($variation['video_package'] ?? null),
        );
    }

    private function mapVideoPackage(mixed $raw): ?VideoPackageData
    {
        if (! is_array($raw)) {
            return null;
        }

        /** @var list<array{time_range: string, action: string, on_screen_text: string, voiceover_line: string, visual_prompt: string}> $scenes */
        $scenes = (array) ($raw['scenes'] ?? []);

        return new VideoPackageData(
            scenes: array_map(
                static fn (array $scene): VideoSceneData => new VideoSceneData(
                    timeRange: (string) $scene['time_range'],
                    action: (string) $scene['action'],
                    onScreenText: (string) $scene['on_screen_text'],
                    voiceoverLine: (string) $scene['voiceover_line'],
                    visualPrompt: (string) $scene['visual_prompt'],
                ),
                $scenes,
            ),
            cleanScript: (string) ($raw['clean_script'] ?? ''),
            soundSuggestion: (string) ($raw['sound_suggestion'] ?? ''),
            targetDurationSeconds: max(15, min(30, (int) ($raw['target_duration_seconds'] ?? 15))),
            creativeStyle: (string) ($raw['creative_style'] ?? 'ugc_native') ?: 'ugc_native',
        );
    }

    /**
     * Varies the research queries per iteration (Prompt2's convention) so a
     * retry gets genuinely fresh context instead of repeating the same search.
     *
     * Capped at {@see TavilyClientInterface} MAX_QUERIES (4). The adapter
     * silently drops anything past the fourth query, so the extra iteration-4/5
     * angles are ROTATED IN rather than appended — a 6-query list would have
     * meant iterations 2-5 all researching the exact same first four terms
     * while appearing to widen.
     *
     * @return list<string>
     */
    private function researchQueries(GenerateSocialMediaContentData $data, int $iteration): array
    {
        $niche = $data->niche ?? $data->topic;
        $trend = $data->keyTrend ?? $data->topic;

        $queries = match (true) {
            $iteration >= 4 => [
                "{$niche} authority sources citations",
                "{$data->topic} social media best practices",
                "{$trend} content examples",
                "{$niche} CapCut trending sounds 2026",
            ],
            $iteration >= 2 => [
                "{$data->topic} {$niche} 2026",
                "{$niche} viral examples social media",
                "{$data->topic} engagement benchmarks",
                "{$niche} short form video Reels TikTok ROI 2026",
            ],
            default => [
                "{$data->topic} {$niche} 2026",
                "{$trend} statistics recent data",
                "{$niche} audience insights trends",
                "{$niche} short form video viral hooks 2026",
            ],
        };

        return array_values(array_unique($queries));
    }

    /**
     * @param  array{name: string, description: ?string}  $company
     * @param  list<array{title: string, url: string, content: string, score: float}>  $research
     * @param  list<array{score: string, current: int, target: int, gap: int, explanation: string}>  $previousWeaknesses
     */
    private function buildContentPrompt(
        GenerateSocialMediaContentData $data,
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
            $data->hook !== null ? "Hook: {$data->hook}" : null,
            $data->keyTrend !== null ? "Key trend to reference: {$data->keyTrend}" : null,
            $data->audience !== null ? "Target audience: {$data->audience}" : null,
            "Business goal: {$data->businessGoal}",
            "Brand voice: {$data->brandVoice}",
            "Funnel stage: {$data->funnelStage}",
            ContentLanguage::tryFrom($data->language)?->outputInstruction()
                ?? "Output language: {$data->language}",
            'Web research context:'."\n".$this->formatResearch($research),
            $iteration === 1
                ? 'This is the first attempt. Generate the best possible content from the start.'
                : $this->formatWeaknesses($iteration, $previousWeaknesses),
            'Write the complete 5-platform package exactly as specified in your instructions.',
        ]));
    }

    /**
     * @param  list<array{score: string, current: int, target: int, gap: int, explanation: string}>  $weaknesses
     */
    private function formatWeaknesses(int $iteration, array $weaknesses): string
    {
        if ($weaknesses === []) {
            return "Iteration {$iteration}. No specific weaknesses recorded — refine broadly.";
        }

        $lines = array_map(
            static fn (array $w): string => "- {$w['score']}: was {$w['current']}, needs {$w['target']}+. Why it failed: {$w['explanation']}",
            $weaknesses,
        );

        return "Iteration {$iteration} of ".ContentQualityEvaluator::MAX_ITERATIONS.". An independent reviewer failed these scores on your previous attempt:\n"
            .implode("\n", $lines)
            ."\nDo NOT repeat the same content — change the angle, hook, or evidence for each failing score while keeping what worked.";
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
     * Deterministic cache key for one AI operation, scoped by every input
     * field that affects the output. `$causer` is deliberately excluded —
     * two different users requesting the identical payload should share the
     * cache entry.
     *
     * @param  array<string, mixed>  $payload
     */
    private function cacheKey(string $operation, array $payload): string
    {
        return 'social_media:ai:'.$operation.':'.md5(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Drops fields that must not take part in a cache key.
     *
     * Keys are normalised to snake_case first because `Data::toArray()` emits
     * PROPERTY names: {@see GenerateSocialMediaContentData} carries
     * `MapInputName` only, so the keys are `imageMode` / `generateVoiceover`,
     * and a naive `array_diff_key` against their snake_case spellings silently
     * excluded nothing at all — every image mode kept its own copy of an
     * identical draft and re-ran the whole quality loop to produce it.
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
}
