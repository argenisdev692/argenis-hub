<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\ValueObjects\ContentFingerprint;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\OutputProfile;

it('fits the output frame to the first clip without stretching it', function (int $width, int $height, float $fps, int $expectedWidth, int $expectedHeight, float $expectedFps): void {
    $probe = new MediaProbe(60_000, 'mov,mp4,m4a,3gp,3g2,mj2', true, true, $width, $height, $fps);

    $profile = OutputProfile::fromFirstSource($probe, 1920, 1080, 60, 48_000, 2);

    expect($profile->width)->toBe($expectedWidth)
        ->and($profile->height)->toBe($expectedHeight)
        ->and($profile->frameRate)->toBe($expectedFps)
        ->and($profile->audioSampleRate)->toBe(48_000)
        ->and($profile->audioChannels)->toBe(2);
})->with([
    '1080p30 stays as is' => [1920, 1080, 30.0, 1920, 1080, 30.0],
    '4K60 scales down' => [3840, 2160, 60.0, 1920, 1080, 60.0],
    '720p is never upscaled' => [1280, 720, 29.97, 1280, 720, 29.97],
    'portrait keeps its ratio with even sides' => [1080, 1920, 30.0, 606, 1080, 30.0],
    'frame rate is capped' => [1920, 1080, 120.0, 1920, 1080, 60.0],
]);

it('accepts a container when any reported family token is allowed', function (string $container, bool $allowed): void {
    $probe = new MediaProbe(1_000, $container, true, false);

    expect($probe->isAllowedContainer(['mov', 'mp4', 'webm', 'matroska']))->toBe($allowed);
})->with([
    'mp4 family' => ['mov,mp4,m4a,3gp,3g2,mj2', true],
    'matroska family' => ['matroska,webm', true],
    'avi' => ['avi', false],
    'image pipe' => ['png_pipe', false],
]);

it('only accepts lowercase SHA-256 digests as fingerprints', function (): void {
    expect((new ContentFingerprint(hash('sha256', 'clip')))->sha256)->toHaveLength(64);

    new ContentFingerprint('not-a-digest');
})->throws(InvalidArgumentException::class);
