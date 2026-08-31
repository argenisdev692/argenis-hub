<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Modules\SocialMedia\Application\DTOs\ContentEvaluationData;
use Modules\SocialMedia\Application\DTOs\GeneratedSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\ImageConceptData;
use Modules\SocialMedia\Application\DTOs\PlatformContentData;
use Modules\SocialMedia\Application\DTOs\ScoreResultData;
use Modules\SocialMedia\Application\DTOs\ScoreSetData;
use Modules\SocialMedia\Domain\Ports\SocialMediaAssetRendererPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentEvaluatorPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentGeneratorPort;
use Modules\SocialMedia\Domain\Services\ContentQualityEvaluator;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Modules\SocialMedia\Infrastructure\Queue\GenerateSocialMediaContentJob;

/*
| The economics of the quality loop, pinned as behaviour.
|
| Images and voiceover are the expensive part of a generation and they used to
| be produced inside the retry loop, so five attempts billed five sets of
| artwork and discarded four. These tests assert the shape that fixes it: the
| loop is text + an independent verdict, and the renderer runs exactly once,
| on the winner.
*/

/**
 * @param  array<string, int>  $values
 */
function socialMediaScoreSet(array $values): ScoreSetData
{
    $result = static fn (string $key): ScoreResultData => new ScoreResultData(
        value: $values[$key],
        threshold: ContentQualityEvaluator::THRESHOLDS[$key],
        passes: $values[$key] >= ContentQualityEvaluator::THRESHOLDS[$key],
        factors: [],
        explanation: "why {$key} landed at {$values[$key]}",
    );

    $evaluation = (new ContentQualityEvaluator)->evaluate($values);

    return new ScoreSetData(
        humanWritingIndex: $result('human_writing_index'),
        viralityScore: $result('virality_score'),
        engagementScore: $result('engagement_score'),
        roiScore: $result('roi_score'),
        trendAlignment: $result('trend_alignment'),
        allScoresPass: $evaluation->allPass,
        overallAverage: $evaluation->overallAverage,
    );
}

function socialMediaDraft(string $headline): GeneratedSocialMediaContentData
{
    $concept = new ImageConceptData(title: 'Title', visual: 'a node network');

    $platform = static fn (string $name): PlatformContentData => new PlatformContentData(
        platform: $name,
        adaptedContent: "{$headline} on {$name}",
        characterCount: 120,
        hashtags: ['#tech'],
        imageConcept: $concept,
    );

    return new GeneratedSocialMediaContentData(
        headline: $headline,
        body: 'Body.',
        callToAction: 'Follow.',
        hashtags: ['#tech'],
        platforms: [
            'linkedin' => $platform('linkedin'),
            'twitter' => $platform('twitter'),
            'instagram' => $platform('instagram'),
            'facebook' => $platform('facebook'),
            'tiktok' => $platform('tiktok'),
        ],
        coverImageConcept: $concept,
        researchSources: [],
        tavilyDataUsed: [],
        provider: 'openai',
    );
}

function socialMediaGenerationData(string $topic): GenerateSocialMediaContentData
{
    return GenerateSocialMediaContentData::from([
        'topic' => $topic,
        'provider' => 'openai',
        'language' => 'en',
        'business_goal' => 'awareness',
        'brand_voice' => 'professional',
        'funnel_stage' => 'tofu',
        'image_mode' => 'full',
        'generate_voiceover' => true,
    ]);
}

/**
 * Records every call so a test can assert on counts. The generator hands back
 * a new draft per iteration; the evaluator replays a queued list of score
 * sets, so a test decides exactly when the loop is allowed to pass.
 *
 * @param  list<array<string, int>>  $verdicts
 * @return array{generator: object, evaluator: object, renderer: object}
 */
function bindSocialMediaLoopDoubles(array $verdicts): array
{
    $generator = new class implements SocialMediaContentGeneratorPort
    {
        public int $calls = 0;

        /** @var list<array{score: string, current: int, target: int, gap: int, explanation: string}> */
        public array $lastWeaknesses = [];

        public function generate(
            string $contentUuid,
            GenerateSocialMediaContentData $data,
            int $iteration = 1,
            array $previousWeaknesses = [],
            ?object $causer = null,
        ): GeneratedSocialMediaContentData {
            $this->calls++;
            $this->lastWeaknesses = $previousWeaknesses;

            return socialMediaDraft("Draft {$iteration}");
        }
    };

    $evaluator = new class($verdicts) implements SocialMediaContentEvaluatorPort
    {
        public int $calls = 0;

        /**
         * @param  list<array<string, int>>  $verdicts
         */
        public function __construct(private array $verdicts) {}

        public function evaluate(
            string $contentUuid,
            GeneratedSocialMediaContentData $draft,
            GenerateSocialMediaContentData $data,
            int $iteration = 1,
            ?object $causer = null,
        ): ContentEvaluationData {
            $this->calls++;

            return new ContentEvaluationData(
                scores: socialMediaScoreSet($this->verdicts[$iteration - 1] ?? array_last($this->verdicts)),
                eeatAnalysis: [
                    'experience_signals' => [],
                    'expertise_signals' => [],
                    'authoritativeness_signals' => [],
                    'trustworthiness_signals' => [],
                ],
                optimizationSuggestions: [],
                aiDetectionRisk: ['value' => 10, 'label' => 'low', 'explanation' => 'fine'],
                evaluatorProvider: 'anthropic',
            );
        }
    };

    $renderer = new class implements SocialMediaAssetRendererPort
    {
        public int $calls = 0;

        public ?string $renderedHeadline = null;

        public function render(
            string $contentUuid,
            GeneratedSocialMediaContentData $draft,
            GenerateSocialMediaContentData $data,
            ?object $causer = null,
        ): GeneratedSocialMediaContentData {
            $this->calls++;
            $this->renderedHeadline = $draft->headline;

            return $draft->withCoverAssets(
                platforms: $draft->platforms,
                coverImagePrompt: '[route a] prompt',
                coverImagePath: 'social-media/ai/cover/rendered.png',
                coverImageUrl: 'https://cdn.example.test/cover.png',
            );
        }
    };

    app()->instance(SocialMediaContentGeneratorPort::class, $generator);
    app()->instance(SocialMediaContentEvaluatorPort::class, $evaluator);
    app()->instance(SocialMediaAssetRendererPort::class, $renderer);

    return ['generator' => $generator, 'evaluator' => $evaluator, 'renderer' => $renderer];
}

/**
 * @param  list<array<string, int>>  $verdicts
 */
function runSocialMediaQualityLoop(array $verdicts): array
{
    $doubles = bindSocialMediaLoopDoubles($verdicts);
    $content = SocialMediaContentEloquentModel::factory()->create(['status' => 'generating']);

    app()->call([
        new GenerateSocialMediaContentJob($content->uuid, socialMediaGenerationData($content->topic)),
        'handle',
    ]);

    return [...$doubles, 'content' => $content->refresh()];
}

/**
 * @return array<string, int>
 */
function socialMediaFailingVerdict(): array
{
    return [
        'human_writing_index' => 50,
        'virality_score' => 50,
        'engagement_score' => 50,
        'roi_score' => 50,
        'trend_alignment' => 50,
    ];
}

/**
 * @return array<string, int>
 */
function socialMediaPassingVerdict(int $value = 85): array
{
    return [
        'human_writing_index' => $value,
        'virality_score' => $value,
        'engagement_score' => $value,
        'roi_score' => $value,
        'trend_alignment' => $value,
    ];
}

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('renders artwork once, after three text iterations, not once per iteration', function (): void {
    $run = runSocialMediaQualityLoop([socialMediaFailingVerdict(), socialMediaFailingVerdict(), socialMediaPassingVerdict()]);

    expect($run['generator']->calls)->toBe(3)
        ->and($run['evaluator']->calls)->toBe(3)
        ->and($run['renderer']->calls)->toBe(1)
        ->and($run['renderer']->renderedHeadline)->toBe('Draft 3');

    expect($run['content']->status->value)->toBe('ready')
        ->and($run['content']->iterations_required)->toBe(3)
        ->and($run['content']->cover_image_path)->toBe('social-media/ai/cover/rendered.png');
});

it('still renders exactly once when every iteration fails its thresholds', function (): void {
    $run = runSocialMediaQualityLoop([socialMediaFailingVerdict()]);

    expect($run['generator']->calls)->toBe(ContentQualityEvaluator::MAX_ITERATIONS)
        ->and($run['renderer']->calls)->toBe(1);

    expect($run['content']->status->value)->toBe('needs_review')
        ->and($run['content']->quality_warning)->toBeTrue();
});

it('renders the highest-scoring draft, not the last one', function (): void {
    $run = runSocialMediaQualityLoop([
        socialMediaPassingVerdict(72),   // fails human_writing_index (75) but scores well
        socialMediaFailingVerdict(),
        socialMediaFailingVerdict(),
        socialMediaFailingVerdict(),
        socialMediaFailingVerdict(),
    ]);

    expect($run['renderer']->renderedHeadline)->toBe('Draft 1')
        ->and($run['content']->overall_score_avg)->toBe(72);
});

it('feeds the judge\'s explanations back into the next attempt', function (): void {
    $run = runSocialMediaQualityLoop([socialMediaFailingVerdict(), socialMediaPassingVerdict()]);

    expect($run['generator']->lastWeaknesses)->not->toBeEmpty()
        ->and(array_column($run['generator']->lastWeaknesses, 'score'))
        ->toContain('human_writing_index')
        ->and($run['generator']->lastWeaknesses[0]['explanation'])
        ->toBe('why human_writing_index landed at 50');
});

it('never bills a single image when no iteration produced a draft', function (): void {
    $renderer = new class implements SocialMediaAssetRendererPort
    {
        public int $calls = 0;

        public function render(
            string $contentUuid,
            GeneratedSocialMediaContentData $draft,
            GenerateSocialMediaContentData $data,
            ?object $causer = null,
        ): GeneratedSocialMediaContentData {
            $this->calls++;

            return $draft;
        }
    };

    $generator = new class implements SocialMediaContentGeneratorPort
    {
        public function generate(
            string $contentUuid,
            GenerateSocialMediaContentData $data,
            int $iteration = 1,
            array $previousWeaknesses = [],
            ?object $causer = null,
        ): GeneratedSocialMediaContentData {
            throw new RuntimeException('provider is down');
        }
    };

    app()->instance(SocialMediaAssetRendererPort::class, $renderer);
    app()->instance(SocialMediaContentGeneratorPort::class, $generator);

    $content = SocialMediaContentEloquentModel::factory()->create(['status' => 'generating']);

    app()->call([
        new GenerateSocialMediaContentJob($content->uuid, socialMediaGenerationData($content->topic)),
        'handle',
    ]);

    expect($renderer->calls)->toBe(0)
        ->and($content->refresh()->status->value)->toBe('needs_review');
});
