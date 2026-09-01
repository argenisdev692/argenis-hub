<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Ai;

use Illuminate\Support\Facades\Cache;
use Modules\Campaigns\Application\Commands\RunCampaignGenerationHandler;
use Modules\Campaigns\Application\DTOs\CampaignDraftData;
use Modules\Campaigns\Application\DTOs\CampaignImageConceptData;
use Modules\Campaigns\Application\DTOs\CampaignTopicIdeaData;
use Modules\Campaigns\Application\DTOs\CampaignVideoPackageData;
use Modules\Campaigns\Application\DTOs\CampaignVideoSceneData;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Application\DTOs\PlatformCampaignContentData;
use Modules\Campaigns\Application\DTOs\SuggestCampaignTopicsData;
use Modules\Campaigns\Domain\Enums\CampaignLanguage;
use Modules\Campaigns\Domain\Exceptions\CampaignGenerationUnavailableException;
use Modules\Campaigns\Domain\Ports\CampaignGeneratorPort;
use Modules\Campaigns\Domain\Ports\CampaignIdeatorPort;
use Modules\Campaigns\Domain\Services\CampaignQualityEvaluator;
use Modules\Campaigns\Infrastructure\Broadcasting\CampaignProgressNotifier;
use Modules\SocialMedia\Infrastructure\Ai\LaravelAiSocialMediaAssistantAdapter;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\Company\CompanyProfile;
use Shared\Infrastructure\Research\TavilyClientInterface;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerOpenException;

/**
 * Single adapter behind both Campaigns TEXT ports — mirrors
 * {@see LaravelAiSocialMediaAssistantAdapter}: one class composing the agents
 * so the research/prompt-assembly plumbing is not duplicated, while each port
 * stays small (ISP) for its own consumer.
 *
 * This class performs exactly ONE writing attempt per {@see self::generate()}
 * call and produces no billed artwork: images and the Reels voiceover belong
 * to {@see CampaignAssetRenderer}, which runs once on the winner. The
 * up-to-5-iteration quality loop is orchestrated by
 * {@see RunCampaignGenerationHandler}, not here.
 *
 * Caching lives HERE, in the module adapter — never in the Shared AI/Tavily
 * clients (those stay pure transport + circuit breaker). `suggestTopics()`
 * always caches (no internal state). `generate()` caches ONLY the first
 * attempt (iteration 1, no previous weaknesses) — from iteration 2 onward the
 * quality loop deliberately targets specific failing scores, so those attempts
 * are never safe to reuse from cache.
 */
final readonly class LaravelAiCampaignAssistantAdapter implements CampaignGeneratorPort, CampaignIdeatorPort
{
    private const int CACHE_TTL_MINUTES = 15;

    public function __construct(
        private AIClientInterface $ai,
        private TavilyClientInterface $research,
        private CampaignProgressNotifier $progress,
    ) {}

    public function suggestTopics(SuggestCampaignTopicsData $data, ?object $causer = null): array
    {
        return Cache::remember(
            $this->cacheKey('suggest-topics', $data->toArray()),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($data): array {
                $company = CompanyProfile::data();
                $niche = $data->niche ?? $company['description'] ?? $company['name'];
                $geo = $this->resolveGeo($data->city, $data->state, $data->country, $data->location, $company);
                $geoLabel = $this->formatGeoLabel($geo);

                $research = $this->research->search(array_values(array_filter([
                    "{$niche} Meta Ads lead generation trends 2026".($geoLabel !== '' ? " {$geoLabel}" : ''),
                    "{$niche} Facebook Instagram ad examples high ROI".($geoLabel !== '' ? " {$geoLabel}" : ''),
                    "{$niche} audience pain points buyers".($geoLabel !== '' ? " {$geoLabel}" : ''),
                    $geoLabel !== '' ? "{$niche} local market video ads {$geoLabel} 2026" : null,
                ])));

                $prompt = implode("\n\n", array_filter([
                    "Niche: {$niche}",
                    $data->audience !== null ? "Target audience: {$data->audience}" : 'Target audience: infer from the niche.',
                    $data->businessGoal !== null ? "Business goal: {$data->businessGoal}" : null,
                    $geoLabel !== '' ? "Geographic location: {$geoLabel}" : null,
                    CampaignLanguage::tryFrom($data->language)?->outputInstruction()
                        ?? "Output language: {$data->language}",
                    'Web research context:'."\n".$this->formatResearch($research),
                    'Generate exactly 10 Meta Ads campaign angles as specified in your instructions.',
                    'Balance TOFU/MOFU/BOFU/LOYALTY. Prefer local-market angles when geography is supplied.',
                ]));

                $response = $this->ai->generateStructured(SuggestCampaignTopicsAgent::class, $prompt, $data->provider);

                return array_map(
                    static fn (array $topic): CampaignTopicIdeaData => new CampaignTopicIdeaData(
                        title: (string) $topic['title'],
                        angle: (string) $topic['angle'],
                        hook: (string) $topic['hook'],
                        platform: (string) $topic['platform'],
                        estimatedVirality: (int) $topic['estimated_virality'],
                        estimatedEngagement: (string) $topic['estimated_engagement'],
                        estimatedRoi: (int) $topic['estimated_roi'],
                        estimatedLeadPotential: (int) $topic['estimated_lead_potential'],
                        difficulty: (string) $topic['difficulty'],
                        whyItWorks: (string) $topic['why_it_works'],
                        keyTrend: (string) $topic['key_trend'],
                        suggestedFormat: (string) $topic['suggested_format'],
                        contentType: (string) $topic['content_type'],
                        funnelStage: (string) $topic['funnel_stage'],
                    ),
                    (array) $response['campaign_topics'],
                );
            },
        );
    }

    public function generate(
        string $campaignUuid,
        GenerateCampaignData $data,
        int $iteration = 1,
        array $previousWeaknesses = [],
        ?object $causer = null,
    ): CampaignDraftData {
        $attempt = fn (): CampaignDraftData => $this->generateAttempt($campaignUuid, $data, $iteration, $previousWeaknesses, $causer);

        if ($iteration !== 1 || $previousWeaknesses !== []) {
            return $attempt();
        }

        return Cache::remember(
            $this->cacheKey('generate', $data->toArray()),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            $attempt,
        );
    }

    /**
     * @param  list<array{score: string, current: int, target: int, gap: int, explanation: string}>  $previousWeaknesses
     */
    private function generateAttempt(
        string $campaignUuid,
        GenerateCampaignData $data,
        int $iteration,
        array $previousWeaknesses,
        ?object $causer,
    ): CampaignDraftData {
        $this->progress->notify($causer, $campaignUuid, 'researching', "Iteration {$iteration}: researching fresh context…", $this->writingProgress($iteration), $iteration);

        $company = CompanyProfile::data();
        $research = $this->research->search($this->researchQueries($data, $iteration));
        $prompt = $this->buildContentPrompt($data, $company, $research, $iteration, $previousWeaknesses);

        $this->progress->notify($causer, $campaignUuid, 'writing', "Iteration {$iteration}: writing the Meta Ads copy…", $this->writingProgress($iteration) + 4, $iteration);

        // An open breaker is not a bad attempt — it is a provider that is down,
        // and the loop must stop rather than burn its remaining iterations on
        // calls that never leave the process.
        try {
            $response = $this->ai->generateStructured(GenerateCampaignAgent::class, $prompt, $data->provider);
        } catch (CircuitBreakerOpenException $exception) {
            throw CampaignGenerationUnavailableException::forService($data->provider, $exception);
        }

        $platforms = [];

        foreach ((array) $response['platforms'] as $platform => $variation) {
            /** @var array<string, mixed> $variation */
            $platforms[$platform] = $this->buildPlatformContent((string) $platform, $variation, $data);
        }

        /** @var array{content: mixed} $response */
        $content = (array) $response['content'];

        return new CampaignDraftData(
            headline: (string) $content['headline'],
            primaryText: (string) $content['primary_text'],
            description: isset($content['description']) ? (string) $content['description'] : null,
            callToAction: (string) $content['call_to_action'],
            hashtags: (array) $content['hashtags'],
            leadFormQuestions: (array) $content['lead_form_questions'],
            targetingSuggestions: (array) $content['targeting_suggestions'],
            platforms: $platforms,
            coverImageConcept: $this->buildImageConcept((array) $response['cover_image_concept']),
            researchSources: (array) $response['research_sources'],
            tavilyDataUsed: (array) $response['tavily_data_used'],
            provider: $data->provider,
        );
    }

    /**
     * @param  array<string, mixed>  $variation
     */
    private function buildPlatformContent(string $platform, array $variation, GenerateCampaignData $data): PlatformCampaignContentData
    {
        return new PlatformCampaignContentData(
            platform: $platform,
            adaptedPrimaryText: (string) $variation['adapted_primary_text'],
            characterCount: (int) $variation['character_count'],
            headline: (string) $variation['headline'],
            description: isset($variation['description']) ? (string) $variation['description'] : null,
            hashtags: (array) $variation['hashtags'],
            imageConcept: $this->buildImageConcept((array) $variation['image_concept']),
            videoPackage: $this->mapVideoPackage($variation['video_package'] ?? null, $data->adFormat),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function buildImageConcept(array $raw): CampaignImageConceptData
    {
        return new CampaignImageConceptData(
            title: (string) ($raw['title'] ?? ''),
            visual: (string) ($raw['visual'] ?? ''),
        );
    }

    private function mapVideoPackage(mixed $raw, string $adFormat): ?CampaignVideoPackageData
    {
        if (! in_array($adFormat, ['reel', 'story'], true) || ! is_array($raw)) {
            return null;
        }

        /** @var list<array{time_range: string, action: string, on_screen_text: string, voiceover_line: string, visual_prompt: string}> $scenes */
        $scenes = (array) ($raw['scenes'] ?? []);

        if ($scenes === []) {
            return null;
        }

        $targetDuration = (int) ($raw['target_duration_seconds'] ?? 15);
        $targetDuration = max(15, min(30, $targetDuration));

        return new CampaignVideoPackageData(
            scenes: array_map(
                static fn (array $scene): CampaignVideoSceneData => new CampaignVideoSceneData(
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
            targetDurationSeconds: $targetDuration,
            creativeStyle: (string) ($raw['creative_style'] ?? 'ugc_native') ?: 'ugc_native',
        );
    }

    /**
     * Varies the research queries per iteration (Prompt2's convention) so a
     * retry gets genuinely fresh context instead of repeating the same search.
     *
     * @return list<string>
     */
    private function researchQueries(GenerateCampaignData $data, int $iteration): array
    {
        $niche = $data->niche ?? $data->topic;
        $company = CompanyProfile::data();
        $geo = $this->resolveGeo($data->city, $data->state, $data->country, $data->location, $company);
        $geoLabel = $this->formatGeoLabel($geo);

        $base = array_values(array_filter([
            "{$data->topic} {$niche} Meta Ads 2026".($geoLabel !== '' ? " {$geoLabel}" : ''),
            $data->keyTrend !== null ? "{$data->keyTrend} statistics recent data".($geoLabel !== '' ? " {$geo['country']}" : '') : null,
            "{$niche} lead generation audience insights".($geoLabel !== '' ? " {$geoLabel}" : ''),
            $geoLabel !== '' ? "{$niche} local market trends {$geoLabel}" : null,
        ]));

        return match (true) {
            $iteration >= 4 => [...$base, "{$niche} authority sources citations", "{$data->topic} best practices".($geoLabel !== '' ? " {$geoLabel}" : '')],
            $iteration >= 2 => [...$base, "{$niche} viral Reels ad examples Facebook Instagram UGC", "{$data->topic} conversion rate benchmarks"],
            default => $base,
        };
    }

    /**
     * @param  array{name: string, description: ?string, city?: ?string, state?: ?string, country?: ?string, address?: ?string}  $company
     * @param  list<array{title: string, url: string, content: string, score: float}>  $research
     * @param  list<array{score: string, current: int, target: int, gap: int, explanation: string}>  $previousWeaknesses
     */
    private function buildContentPrompt(
        GenerateCampaignData $data,
        array $company,
        array $research,
        int $iteration,
        array $previousWeaknesses,
    ): string {
        $geo = $this->resolveGeo($data->city, $data->state, $data->country, $data->location, $company);
        $geoLabel = $this->formatGeoLabel($geo);
        $needsVideo = in_array($data->adFormat, ['reel', 'story'], true);

        return implode("\n\n", array_filter([
            "Company: {$company['name']}",
            $company['description'] !== null ? "Company description: {$company['description']}" : null,
            "Topic: {$data->topic}",
            $data->angle !== null ? "Angle: {$data->angle}" : null,
            $data->hook !== null ? "Hook: {$data->hook}" : null,
            $data->keyTrend !== null ? "Key trend to reference: {$data->keyTrend}" : null,
            $data->audience !== null ? "Target audience: {$data->audience}" : null,
            $geoLabel !== '' ? "Geographic location: {$geoLabel}" : null,
            $geo['location'] !== null && $geo['location'] !== '' ? "Address/locality: {$geo['location']}" : null,
            "Business goal: {$data->businessGoal}",
            "Brand voice: {$data->brandVoice}",
            "Funnel stage: {$data->funnelStage}",
            "Meta platform: {$data->platform}",
            "Ad format: {$data->adFormat}",
            CampaignLanguage::tryFrom($data->language)?->outputInstruction()
                ?? "Output language: {$data->language}",
            $needsVideo
                ? 'Ad format requires a CapCut video_package on EVERY platform variant (stage-aware 15-30s, creative_style=ugc_native).'
                : 'Ad format does NOT use video — set video_package to null on every platform variant.',
            'Web research context:'."\n".$this->formatResearch($research),
            $iteration === 1
                ? 'This is the first attempt. Generate the best possible campaign from the start.'
                : $this->formatWeaknesses($iteration, $previousWeaknesses),
            'Write the complete Facebook + Instagram Meta Ads package exactly as specified in your instructions.',
        ]));
    }

    /**
     * @param  array{name: string, description: ?string, city?: ?string, state?: ?string, country?: ?string, address?: ?string}  $company
     * @return array{city: ?string, state: ?string, country: ?string, location: ?string}
     */
    private function resolveGeo(
        ?string $city,
        ?string $state,
        ?string $country,
        ?string $location,
        array $company,
    ): array {
        return [
            'city' => $city ?: ($company['city'] ?? null),
            'state' => $state ?: ($company['state'] ?? null),
            'country' => $country ?: ($company['country'] ?? null),
            'location' => $location ?: ($company['address'] ?? null),
        ];
    }

    /**
     * @param  array{city: ?string, state: ?string, country: ?string, location?: ?string}  $geo
     */
    private function formatGeoLabel(array $geo): string
    {
        return implode(', ', array_values(array_filter([
            $geo['city'] ?? null,
            $geo['state'] ?? null,
            $geo['country'] ?? null,
        ], static fn (?string $part): bool => $part !== null && $part !== '')));
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

        return "Iteration {$iteration} of ".CampaignQualityEvaluator::MAX_ITERATIONS.". An independent auditor failed these scores on your previous attempt:\n"
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
        return 'campaigns:ai:'.$operation.':'.md5(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * The write owns the first slice of each iteration's share of the 0-80%
     * band the loop reports in; the judge reports just after it, and the
     * render pass owns 80-100%.
     */
    private function writingProgress(int $iteration): int
    {
        return (int) round((($iteration - 1) / CampaignQualityEvaluator::MAX_ITERATIONS) * 80) + 4;
    }
}
