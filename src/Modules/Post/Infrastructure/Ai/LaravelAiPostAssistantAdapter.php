<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Ai;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Post\Application\DTOs\GenerateContentVariantData;
use Modules\Post\Application\DTOs\GeneratedPostContentData;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostTopicIdeaData;
use Modules\Post\Application\DTOs\ReelPackageData;
use Modules\Post\Application\DTOs\ReelSceneData;
use Modules\Post\Application\DTOs\SocialCopyData;
use Modules\Post\Application\DTOs\SuggestPostTopicsData;
use Modules\Post\Domain\Ports\PostContentGeneratorPort;
use Modules\Post\Domain\Ports\PostTopicIdeatorPort;
use Modules\Post\Domain\Ports\ReelPackageGeneratorPort;
use Modules\Post\Domain\Ports\SocialCopyGeneratorPort;
use Modules\Post\Domain\Services\PostContentQualityEvaluator;
use Modules\Post\Infrastructure\Broadcasting\PostAiGenerationProgress;
use Shared\Domain\Ports\SpeechSynthesizerPort;
use Shared\Domain\Ports\StoragePort;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\Branding\BrandPalette;
use Shared\Infrastructure\Company\CompanyProfile;
use Shared\Infrastructure\Research\TavilyClientInterface;
use Throwable;

/**
 * Single adapter behind all four AI ports the Post module needs — they share
 * the same underlying `laravel/ai` + Tavily infrastructure, so one class
 * composing the agents avoids duplicating the research/prompt-assembly
 * plumbing while each port stays small (ISP) for its own consumer.
 *
 * Caching lives HERE, in the module adapter — never in the Shared AI/Tavily
 * clients. `suggestTopics` / social / reel cache the full result. `generate()`
 * runs an up-to-5-iteration quality loop and caches only the final best
 * attempt keyed on the input payload (iteration state is never part of the
 * cache key).
 */
final readonly class LaravelAiPostAssistantAdapter implements PostContentGeneratorPort, PostTopicIdeatorPort, ReelPackageGeneratorPort, SocialCopyGeneratorPort
{
    private const int CACHE_TTL_MINUTES = 15;

    public function __construct(
        private AIClientInterface $ai,
        private TavilyClientInterface $research,
        private StoragePort $storage,
        private SpeechSynthesizerPort $speech,
        private PostContentQualityEvaluator $evaluator = new PostContentQualityEvaluator,
    ) {}

    public function suggestTopics(SuggestPostTopicsData $data, ?object $causer = null): array
    {
        return Cache::remember(
            $this->cacheKey('suggest-topics', $data->toArray()),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($data, $causer): array {
                $this->broadcast($causer, 'topics', 'researching', 'Researching current trends…', 20);

                $company = CompanyProfile::data();
                $niche = $data->topic ?? $company['description'] ?? $company['name'];

                $research = $this->research->search([
                    "{$niche} trends 2026",
                    "{$niche} viral content ideas",
                    "{$niche} audience pain points",
                ]);

                $prompt = $this->buildTopicsPrompt($data, $company, $research);

                $this->broadcast($causer, 'topics', 'generating', 'Drafting topic ideas…', 60);

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

                $this->broadcast($causer, 'topics', 'done', 'Topic ideas ready.', 100);

                return $ideas;
            },
        );
    }

    public function generate(GeneratePostContentData $data, ?object $causer = null): GeneratedPostContentData
    {
        return Cache::remember(
            $this->cacheKey('generate', $data->toArray()),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($data, $causer): GeneratedPostContentData {
                $company = CompanyProfile::data();

                $bestResponse = null;
                $bestThresholdScores = [];
                $bestOverallAverage = -1;
                $previousWeaknesses = [];
                $iterationsRan = 0;
                $allScoresPass = false;

                for ($iteration = 1; $iteration <= PostContentQualityEvaluator::MAX_ITERATIONS; $iteration++) {
                    $iterationsRan = $iteration;
                    $progressBase = (int) round((($iteration - 1) / PostContentQualityEvaluator::MAX_ITERATIONS) * 80);

                    $this->broadcast(
                        $causer,
                        'content',
                        'researching',
                        "Iteration {$iteration}: researching…",
                        $progressBase + 5,
                    );

                    try {
                        $research = $this->research->search(
                            $this->researchQueriesForIteration($data->topic, $data->keyTrend, $iteration),
                        );
                        $prompt = $this->buildContentPrompt($data, $company, $research, $iteration, $previousWeaknesses);

                        $this->broadcast(
                            $causer,
                            'content',
                            'writing',
                            "Iteration {$iteration}: writing the blog draft…",
                            $progressBase + 15,
                        );

                        $response = $this->ai->generateStructured(GeneratePostContentAgent::class, $prompt, $data->provider);
                    } catch (Throwable $exception) {
                        Log::warning('post.ai.generation.iteration_failed', [
                            'iteration' => $iteration,
                            'error' => $exception->getMessage(),
                        ]);

                        continue;
                    }

                    /** @var array<string, mixed> $rawScores */
                    $rawScores = (array) $response['scores'];
                    $thresholdScores = [
                        'human_writing_index' => (int) ($rawScores['human_writing_index'] ?? 0),
                        'eeat_score' => (int) ($rawScores['eeat_score'] ?? 0),
                        'virality_score' => (int) ($rawScores['virality_score'] ?? 0),
                        'roi_score' => (int) ($rawScores['roi_score'] ?? 0),
                        'seo_score' => (int) ($rawScores['seo_score'] ?? 0),
                    ];

                    $evaluation = $this->evaluator->evaluate($thresholdScores);

                    if ($evaluation->overallAverage > $bestOverallAverage) {
                        $bestOverallAverage = $evaluation->overallAverage;
                        $bestResponse = $response;
                        $bestThresholdScores = $thresholdScores;
                    }

                    if ($evaluation->allPass) {
                        $allScoresPass = true;
                        break;
                    }

                    $previousWeaknesses = $this->evaluator->identifyWeaknesses(
                        $thresholdScores,
                        array_map(static fn (int $value): string => "Scored {$value}", $thresholdScores),
                    );
                }

                if ($bestResponse === null) {
                    throw new \RuntimeException('Post content generation failed on every iteration.');
                }

                /** @var array{title: string, visual: string} $concept */
                $concept = (array) $bestResponse['cover_image_concept'];
                $imagePrompts = $this->buildLayeredImagePrompts((string) $concept['title'], (string) $concept['visual']);

                $coverImagePath = null;
                if ($data->generateCoverImage) {
                    $this->broadcast($causer, 'content', 'image', 'Generating the on-brand cover image…', 90);
                    $coverImagePath = $this->generateAndStoreCoverImage(
                        (string) $concept['title'],
                        (string) $concept['visual'],
                    );
                }

                $coverImageUrl = $coverImagePath !== null ? $this->storage->publicUrl($coverImagePath) : null;

                /** @var array<string, mixed> $scores */
                $scores = (array) $bestResponse['scores'];
                /** @var array{primary_keyword: string, lsi_keywords: list<string>} $seoAnalysis */
                $seoAnalysis = (array) $bestResponse['seo_analysis'];

                $qualityWarning = ! $allScoresPass;

                $this->broadcast(
                    $causer,
                    'content',
                    'done',
                    $qualityWarning ? 'Best attempt ready for review.' : 'Draft ready — all scores passed.',
                    100,
                );

                return new GeneratedPostContentData(
                    title: (string) $bestResponse['title'],
                    content: (string) $bestResponse['content'],
                    excerpt: (string) $bestResponse['excerpt'],
                    metaTitle: (string) $bestResponse['meta_title'],
                    metaDescription: (string) $bestResponse['meta_description'],
                    metaKeywords: (string) $bestResponse['meta_keywords'],
                    coverImagePath: $coverImagePath,
                    coverImageUrl: $coverImageUrl,
                    imagePrompts: $imagePrompts,
                    provider: $data->provider,
                    seoScore: $bestThresholdScores['seo_score'],
                    eeatScore: $bestThresholdScores['eeat_score'],
                    viralityScore: $bestThresholdScores['virality_score'],
                    roiScore: $bestThresholdScores['roi_score'],
                    humanWritingIndex: $bestThresholdScores['human_writing_index'],
                    aiDetectionRisk: (int) ($scores['ai_detection_risk'] ?? 0),
                    allScoresPass: $allScoresPass,
                    iterationsRequired: $iterationsRan,
                    qualityWarning: $qualityWarning,
                    qualityWarningMessage: $qualityWarning
                        ? 'Maximum iterations reached — showing the best attempt for manual review.'
                        : null,
                    scores: $scores,
                    optimizationSuggestions: (array) $bestResponse['optimization_suggestions'],
                    seoAnalysis: $seoAnalysis,
                );
            },
        );
    }

    public function generateSocialCopy(GenerateContentVariantData $data, ?object $causer = null): SocialCopyData
    {
        return Cache::remember(
            $this->cacheKey('generate-social-copy', $data->toArray()),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($data, $causer): SocialCopyData {
                $this->broadcast($causer, 'social', 'researching', 'Researching current trends…', 25);

                $company = CompanyProfile::data();
                $research = $this->research->search($this->researchQueries($data->topic, $data->keyTrend));
                $prompt = $this->buildVariantPrompt(
                    $data,
                    $company,
                    $research,
                    'Write the LinkedIn post and the Instagram/Facebook caption exactly as specified in your instructions.',
                );

                $this->broadcast($causer, 'social', 'writing', 'Writing the social copy…', 70);

                $response = $this->ai->generateStructured(GenerateSocialCopyAgent::class, $prompt, $data->provider);

                $this->broadcast($causer, 'social', 'done', 'Social copy ready.', 100);

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
                $this->broadcast($causer, 'reel', 'researching', 'Researching current trends…', 15);

                $company = CompanyProfile::data();
                $research = $this->research->search($this->researchQueries($data->topic, $data->keyTrend));
                $prompt = $this->buildVariantPrompt(
                    $data,
                    $company,
                    $research,
                    'Write the complete Reel/TikTok package exactly as specified in your instructions.',
                );

                $this->broadcast($causer, 'reel', 'writing', 'Writing the Reel/TikTok script…', 45);

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

                $this->broadcast($causer, 'reel', 'voiceover', 'Synthesizing the AI voiceover…', 80);

                $voiceoverAudioUrl = $this->generateAndStoreVoiceover($cleanScript);

                $this->broadcast($causer, 'reel', 'done', 'Reel package ready.', 100);

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
     * Pushes a real-time progress tick to the causer's own private channel
     * (see {@see PostAiGenerationProgress}). Silently a no-op when there is no
     * authenticated causer (e.g. a future system-triggered call) — the
     * generation itself never depends on the socket push succeeding.
     */
    private function broadcast(?object $causer, string $flow, string $stage, string $message, int $progress): void
    {
        if (! $causer instanceof Authenticatable) {
            return;
        }

        try {
            broadcast(new PostAiGenerationProgress(
                userId: (int) $causer->getAuthIdentifier(),
                flow: $flow,
                stage: $stage,
                message: $message,
                progress: $progress,
            ));
        } catch (Throwable $exception) {
            Log::warning('post.ai.broadcast_failed', ['message' => $exception->getMessage()]);
        }
    }

    /**
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
     * @param  array{name: string, description: ?string}  $company
     * @param  list<array{title: string, url: string, content: string, score: float}>  $research
     */
    private function buildTopicsPrompt(SuggestPostTopicsData $data, array $company, array $research): string
    {
        return implode("\n\n", array_filter([
            "Company: {$company['name']}",
            $company['description'] !== null ? "Company description: {$company['description']}" : null,
            $data->topic !== null ? "Requested topic steer: {$data->topic}" : 'No specific topic given — propose a broad spread across the company\'s niche.',
            'Web research context:'."\n".$this->formatResearch($research),
            'Generate exactly 10 blog topic ideas as specified in your instructions.',
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
    private function formatIterationFeedback(int $iteration, array $weaknesses): ?string
    {
        if ($iteration === 1 || $weaknesses === []) {
            return "Current iteration: {$iteration} of ".PostContentQualityEvaluator::MAX_ITERATIONS
                .'. First attempt — all scores must meet their thresholds.';
        }

        $lines = array_map(
            static fn (array $w): string => "- {$w['score']}: was {$w['current']}, needs {$w['target']}+. {$w['explanation']}",
            $weaknesses,
        );

        return "Iteration {$iteration} of ".PostContentQualityEvaluator::MAX_ITERATIONS
            .". Previous attempt failed these scores:\n"
            .implode("\n", $lines)
            ."\nDo NOT repeat the same content — change the hook, evidence, or CTA for each failing score.";
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
     * Deterministic BrandPalette-locked prompts for separate layer generation
     * (background plate + content/foreground). Always returned to the client.
     *
     * @return array{background: string, content: string}
     */
    private function buildLayeredImagePrompts(string $title, string $visual): array
    {
        $background = BrandPalette::BACKGROUND;
        $primaryAccent = BrandPalette::PRIMARY_ACCENT;
        $secondaryAccent = BrandPalette::SECONDARY_ACCENT;

        return [
            'background' => <<<PROMPT
                Abstract premium dark-mode background only, no objects, no people, no text, no logo, no watermark.
                Deep navy base ({$background}) with soft vertical cinematic gradient, faint geometric grid, subtle film grain.
                Soft indigo glow ({$primaryAccent}) from upper-left, lilac haze ({$secondaryAccent}) lower-right, low contrast, wide negative space in center for later compositing.
                Editorial tech aesthetic, 16:9, 4k, photorealistic lighting, empty center stage.
                PROMPT,
            'content' => <<<PROMPT
                Isolated subject on pure transparent or pure black cutout-ready background: {$visual},
                rendered as glowing 3D glass-and-neon in electric indigo ({$primaryAccent}) with soft lilac accents ({$secondaryAccent}),
                rim light, subtle reflections, sharp focus, depth of field.
                Optional single short title below in clean bold sans-serif: "{$title}".
                No paragraphs, no extra UI, no watermark, centered, Apple-keynote quality, 16:9.
                PROMPT,
        ];
    }

    /**
     * Composite cover (background + content + title) for Gemini Imagen when
     * `generate_cover_image` is true — see {@see BrandPalette}.
     */
    private function generateAndStoreCoverImage(string $title, string $visual): ?string
    {
        $background = BrandPalette::BACKGROUND;
        $primaryAccent = BrandPalette::PRIMARY_ACCENT;
        $secondaryAccent = BrandPalette::SECONDARY_ACCENT;

        $prompt = <<<PROMPT
            Premium tech social media graphic, dark mode, minimalist, high-end.
            Background: deep navy blue ({$background}) with a subtle gradient
            and soft cinematic lighting from top. Centered composition: {$visual},
            rendered as a glowing 3D glass-and-neon element in electric purple
            ({$primaryAccent}) with soft accents in ({$secondaryAccent}),
            soft rim light, subtle reflections. Below it, one short title in clean
            bold sans-serif: "{$title}". Generous negative space, thin geometric
            accent lines, faint grid texture. Aesthetic: engineered, editorial,
            Apple-keynote quality. Sharp focus, depth of field, 4k. No extra text,
            no paragraphs, no watermark.
            PROMPT;

        $image = $this->ai->generateImage($prompt, provider: null, size: '16:9', quality: 'high');

        $extension = str_contains($image['mime'], 'png') ? 'png' : 'jpg';
        $path = 'posts/ai/'.Str::uuid7().'.'.$extension;

        return $this->storage->put($path, base64_decode($image['base64'], true) ?: '', 'public');
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
