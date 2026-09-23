<?php

declare(strict_types=1);

use Modules\VideoEdits\Application\Pipeline\DecisionProducerRegistry;
use Modules\VideoEdits\Application\Pipeline\Producers\ManualRangeDecisionProducer;
use Modules\VideoEdits\Application\Pipeline\Producers\SilenceDecisionProducer;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Ports\CutDecisionProducer;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;
use Modules\VideoEdits\Tests\Support\FakeVideoEditor;

beforeEach(function (): void {
    $this->editor = new FakeVideoEditor;
    app()->instance(VideoEditorPort::class, $this->editor);
});

/**
 * @param  array<string, mixed>  $parameters
 */
function decisionContext(VideoEditMode $mode, array $parameters): DecisionContext
{
    return new DecisionContext(
        mode: $mode,
        parameters: $parameters,
        workingPath: '/workspace/edit/merged.mp4',
        workingProbe: new MediaProbe(60_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
    );
}

/**
 * @param  list<CutDecisionProducer>  $producers
 * @return list<string>
 */
function producerNames(array $producers): array
{
    return array_map(static fn (CutDecisionProducer $producer): string => $producer->name(), $producers);
}

it('selects producers from the mode and the requested settings', function (VideoEditMode $mode, array $parameters, array $expected): void {
    $registry = app(DecisionProducerRegistry::class);

    expect(producerNames($registry->forContext(decisionContext($mode, $parameters))))->toBe($expected);
})->with([
    'merge never cuts' => [VideoEditMode::Merge, ['silence_removal' => ['enabled' => true], 'manual_ranges' => [['start_ms' => 0, 'end_ms' => 1]]], []],
    'silence only' => [VideoEditMode::AutoEdit, ['silence_removal' => ['enabled' => true, 'threshold_seconds' => 1.0], 'manual_ranges' => []], ['silence_detector']],
    'ranges only' => [VideoEditMode::AutoEdit, ['silence_removal' => ['enabled' => false], 'manual_ranges' => [['start_ms' => 0, 'end_ms' => 500]]], ['manual']],
    'both' => [VideoEditMode::AutoEdit, ['silence_removal' => ['enabled' => true, 'threshold_seconds' => 2.0], 'manual_ranges' => [['start_ms' => 0, 'end_ms' => 500]]], ['silence_detector', 'manual']],
]);

it('turns detected silences into system decisions using the requested threshold', function (): void {
    $this->editor->silences = [new TimeRange(1_000, 3_000), new TimeRange(40_000, 42_500)];

    $decisions = app(SilenceDecisionProducer::class)->produce(decisionContext(VideoEditMode::AutoEdit, [
        'silence_removal' => ['enabled' => true, 'threshold_seconds' => 2.0],
    ]));

    $call = $this->editor->callsTo('detectSilences')[0];

    expect($decisions)->toHaveCount(2)
        ->and($decisions[0]->reason)->toBe(CutReason::Silence)
        ->and($decisions[0]->origin)->toBe(DecisionOrigin::SystemDetection)
        ->and([$decisions[1]->startMs, $decisions[1]->endMs])->toBe([40_000, 42_500])
        // The fake screen is still throughout, so neither silence is a tool wait.
        ->and($decisions[0]->evidence)->toBe(['threshold_ms' => 2_000, 'screen_activity' => 0.0])
        ->and($call['threshold']->milliseconds)->toBe(2_000)
        ->and($call['noiseFloorDb'])->toBe(-40);
});

it('measures how busy the screen was during each silence', function (): void {
    // 10–20 s: the picture freezes only for 12–14 s, so it moves 80 % of the silence.
    // 40–44 s: frozen throughout — dead air.
    $this->editor->silences = [new TimeRange(10_000, 20_000), new TimeRange(40_000, 44_000)];
    $this->editor->frozen = [new TimeRange(12_000, 14_000), new TimeRange(30_000, 50_000)];

    $decisions = app(SilenceDecisionProducer::class)->produce(decisionContext(VideoEditMode::AutoEdit, [
        'silence_removal' => ['enabled' => true, 'threshold_seconds' => 1.0],
    ]));

    expect($decisions[0]->evidence['screen_activity'])->toBe(0.8)
        ->and($decisions[1]->evidence['screen_activity'])->toBe(0.0)
        ->and($this->editor->callsTo('detectFrozenFrames')[0]['noiseDb'])->toBe(-50);
});

it('still cuts every silence when the screen check fails or is switched off', function (bool $enabled): void {
    config()->set('video-edit.screen_activity.enabled', $enabled);
    $this->editor->silences = [new TimeRange(10_000, 20_000)];
    $this->editor->failOn = 'detectFrozenFrames';
    $this->editor->failure = new RuntimeException('freezedetect crashed');

    $decisions = app(SilenceDecisionProducer::class)->produce(decisionContext(VideoEditMode::AutoEdit, [
        'silence_removal' => ['enabled' => true, 'threshold_seconds' => 1.0],
    ]));

    expect($decisions)->toHaveCount(1)
        ->and($decisions[0]->evidence)->not->toHaveKey('screen_activity');
})->with(['failed check' => true, 'switched off' => false]);

it('falls back to the default silence threshold', function (): void {
    app(SilenceDecisionProducer::class)->produce(decisionContext(VideoEditMode::AutoEdit, [
        'silence_removal' => ['enabled' => true, 'threshold_seconds' => null],
    ]));

    expect($this->editor->callsTo('detectSilences')[0]['threshold']->milliseconds)->toBe(1_000);
});

it('turns manual ranges into user decisions that remember their index', function (): void {
    $decisions = (new ManualRangeDecisionProducer)->produce(decisionContext(VideoEditMode::AutoEdit, [
        'manual_ranges' => [
            ['start_ms' => 12_400, 'end_ms' => 14_800, 'note' => 'retake'],
            ['start_ms' => 70_000, 'end_ms' => 71_000, 'note' => null],
        ],
    ]));

    expect($decisions)->toHaveCount(2)
        ->and($decisions[0]->reason)->toBe(CutReason::Manual)
        ->and($decisions[0]->origin)->toBe(DecisionOrigin::User)
        ->and($decisions[1]->evidence)->toBe(['range_index' => 1, 'note' => null])
        ->and([$decisions[1]->startMs, $decisions[1]->endMs])->toBe([70_000, 71_000]);
});
