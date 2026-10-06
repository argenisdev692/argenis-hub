<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\ValueObjects\VoiceChain;

it('contributes nothing when every stage is off', function (): void {
    // The correct state for a recording that came through OBS: its filters are
    // already baked into the file and repeating them only degrades the audio.
    expect((new VoiceChain)->filters())->toBe('')
        ->and((new VoiceChain)->isEmpty())->toBeTrue();
});

it('renders the chain in OBS order: suppress, compress, expand, limit', function (): void {
    $filters = (new VoiceChain(
        rnnoiseModel: 'sh.rnnn',
        rnnoiseMix: 1.0,
        compressor: ['threshold_db' => -18.0, 'ratio' => 3.0, 'attack_ms' => 6.0, 'release_ms' => 100.0, 'makeup_db' => 3.0, 'knee' => 2.82843],
        expander: ['threshold_db' => -40.0, 'ratio' => 4.0, 'attack_ms' => 10.0, 'release_ms' => 100.0, 'range_db' => -40.0],
        limiter: ['ceiling_db' => -3.0, 'attack_ms' => 5.0, 'release_ms' => 60.0],
    ))->filters();

    expect($filters)->toStartWith(',arnndn=m=')
        ->and($filters)->toContain('acompressor=')
        ->toContain('agate=')
        ->toContain('alimiter=')
        // Order is the whole point: it is the order the guide's figures were
        // measured with, and swapping any two of them changes the result.
        ->and(strpos($filters, 'arnndn'))->toBeLessThan(strpos($filters, 'acompressor'))
        ->and(strpos($filters, 'acompressor'))->toBeLessThan(strpos($filters, 'agate'))
        ->and(strpos($filters, 'agate'))->toBeLessThan(strpos($filters, 'alimiter'));
});

it('converts the guide decibels into the linear numbers FFmpeg wants', function (): void {
    // Pasting -18 into acompressor would not be a threshold at all: it is a
    // linear amplitude, and -18 there means -18 dBFS of headroom and silence.
    $filters = (new VoiceChain(
        compressor: ['threshold_db' => -18.0, 'ratio' => 3.0, 'attack_ms' => 6.0, 'release_ms' => 100.0, 'makeup_db' => 3.0, 'knee' => 2.82843],
    ))->filters();

    expect($filters)->toContain('threshold=0.12589')   // 10^(-18/20)
        ->and($filters)->toContain('makeup=1.41254');   // 10^(+3/20)
});

it('turns the expander into a deep downward gate, since FFmpeg has none', function (): void {
    $filters = (new VoiceChain(
        expander: ['threshold_db' => -40.0, 'ratio' => 4.0, 'attack_ms' => 10.0, 'release_ms' => 100.0, 'range_db' => -40.0],
    ))->filters();

    expect($filters)->toContain('agate=threshold=0.01000')
        ->toContain('ratio=4.00000')
        ->toContain('range=0.01000');   // deepest attenuation, matching the guide
});

it('switches off the limiter auto-level, which would fight loudnorm', function (): void {
    // `alimiter` defaults to level=true and rescales to level_out. Left on, it
    // silently wins over the loudnorm that runs after it.
    $filters = (new VoiceChain(
        limiter: ['ceiling_db' => -3.0, 'attack_ms' => 5.0, 'release_ms' => 60.0],
    ))->filters();

    expect($filters)->toContain('limit=0.70795')     // 10^(-3/20)
        ->toContain('level=false');
});

it('escapes a Windows model path for the FFmpeg tokenizer, not a shell', function (): void {
    // escapeshellarg() is wrong here: FFmpeg's own av_get_token splits on ':',
    // so a drive letter would silently truncate the path at "C". Escaping the
    // colon and backslash is what makes the filter parse.
    $filters = (new VoiceChain(rnnoiseModel: 'C:\\models\\rnnoise\\sh.rnnn'))->filters();

    expect($filters)->toBe(",arnndn=m='C\\:\\\\models\\\\rnnoise\\\\sh.rnnn'");
});

it('skips the RNNoise stage when no model is configured', function (): void {
    // A model OBS cannot export: the file is simply absent, and a missing model
    // is a config problem rather than a reason to fail a render.
    expect((new VoiceChain(rnnoiseModel: null))->filters())->toBe('')
        ->and((new VoiceChain(rnnoiseModel: '   '))->filters())->toBe('');
});

it('renders each stage on its own', function (): void {
    expect((new VoiceChain(
        compressor: ['threshold_db' => -18.0, 'ratio' => 3.0, 'attack_ms' => 6.0, 'release_ms' => 100.0, 'makeup_db' => 3.0, 'knee' => 2.82843],
    ))->filters())->toStartWith(',acompressor=')
        ->and((new VoiceChain(
            expander: ['threshold_db' => -40.0, 'ratio' => 4.0, 'attack_ms' => 10.0, 'release_ms' => 100.0, 'range_db' => -40.0],
        ))->filters())->toStartWith(',agate=')
        ->and((new VoiceChain(
            limiter: ['ceiling_db' => -3.0, 'attack_ms' => 5.0, 'release_ms' => 60.0],
        ))->filters())->toStartWith(',alimiter=');
});
