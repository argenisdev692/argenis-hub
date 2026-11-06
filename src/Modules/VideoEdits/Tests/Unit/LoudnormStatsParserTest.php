<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Services\LoudnormStatsParser;

it('reads the five numbers loudnorm prints for the second pass', function (): void {
    $stderr = <<<'STDERR'
        [Parsed_loudnorm_0 @ 0000025c7c4ecf00]
        {
            "input_i" : "-25.22",
            "input_tp" : "-17.99",
            "input_lra" : "3.70",
            "input_thresh" : "-35.69",
            "output_i" : "-22.96",
            "output_tp" : "-14.42",
            "output_lra" : "5.60",
            "output_thresh" : "-33.41",
            "normalization_type" : "dynamic",
            "target_offset" : "6.96"
        }
        [out#0/null @ 0000025c7c42ce80] video:0KiB audio:4500KiB
        STDERR;

    $measurement = (new LoudnormStatsParser)->parse($stderr);

    expect($measurement)->not->toBeNull()
        ->and($measurement->integratedLufs)->toBe(-25.22)
        ->and($measurement->truePeakDb)->toBe(-17.99)
        ->and($measurement->loudnessRange)->toBe(3.70)
        ->and($measurement->thresholdLufs)->toBe(-35.69)
        ->and($measurement->targetOffset)->toBe(6.96);
});

it('uses the last block when stderr carries more than one measurement', function (): void {
    $first = '{"input_i":"-30.00","input_tp":"-10.00","input_lra":"4.00","input_thresh":"-40.00","target_offset":"1.00"}';
    $last = '{"input_i":"-20.00","input_tp":"-5.00","input_lra":"6.00","input_thresh":"-35.00","target_offset":"2.00"}';

    $measurement = (new LoudnormStatsParser)->parse($first.' trailer '.$last);

    expect($measurement?->integratedLufs)->toBe(-20.00)
        ->and($measurement?->targetOffset)->toBe(2.00);
});

it('refuses a silent file instead of turning it up by fifty decibels', function (): void {
    $stderr = '{"input_i":"-inf","input_tp":"-inf","input_lra":"0.00","input_thresh":"-inf","target_offset":"0.00"}';

    expect((new LoudnormStatsParser)->parse($stderr))->toBeNull();
});

it('refuses a measurement that is effectively below the speech floor', function (): void {
    $stderr = '{"input_i":"-70.00","input_tp":"-70.00","input_lra":"0.00","input_thresh":"-70.00","target_offset":"0.00"}';

    expect((new LoudnormStatsParser)->parse($stderr))->toBeNull();
});

it('refuses a measurement with a missing or non-numeric field', function (): void {
    $missing = '{"input_i":"-20.00","input_tp":"-5.00","input_lra":"6.00","input_thresh":"-35.00"}';
    $notNumeric = '{"input_i":"loud","input_tp":"-5.00","input_lra":"6.00","input_thresh":"-35.00","target_offset":"0.00"}';

    expect((new LoudnormStatsParser)->parse($missing))->toBeNull()
        ->and((new LoudnormStatsParser)->parse($notNumeric))->toBeNull();
});

it('returns null when the block has no measurement at all', function (): void {
    expect((new LoudnormStatsParser)->parse('ffmpeg version 9.0.2'))->toBeNull()
        ->and((new LoudnormStatsParser)->parse('{"input_i":"-20.00"}'))->toBeNull()
        ->and((new LoudnormStatsParser)->parse(''))->toBeNull();
});
