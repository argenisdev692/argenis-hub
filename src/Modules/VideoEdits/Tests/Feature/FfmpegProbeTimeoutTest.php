<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;

/**
 * ffprobe reads a user-uploaded file, so a stalled probe must end at the
 * configured bound and read as unusable media — never pin a worker forever.
 * A stub binary that only sleeps stands in for a crafted file.
 */
beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'video-edit-probe-test-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->directory);

    if (PHP_OS_FAMILY === 'Windows') {
        $this->stub = $this->directory.DIRECTORY_SEPARATOR.'ffprobe.bat';
        file_put_contents($this->stub, "@ping -n 30 127.0.0.1 >nul\r\n");
    } else {
        $this->stub = $this->directory.DIRECTORY_SEPARATOR.'ffprobe';
        file_put_contents($this->stub, "#!/bin/sh\nsleep 30\n");
        chmod($this->stub, 0755);
    }

    config()->set('laravel-ffmpeg.ffprobe.binaries', $this->stub);
    config()->set('video-edit.limits.probe_timeout_seconds', 1);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('stops a stalled probe at the configured timeout and reports no video', function (): void {
    $startedAt = microtime(true);

    $probe = app(VideoEditorPort::class)->probe($this->directory.DIRECTORY_SEPARATOR.'clip.mp4');

    expect($probe->hasVideo)->toBeFalse()
        ->and(microtime(true) - $startedAt)->toBeLessThan(15);
});
