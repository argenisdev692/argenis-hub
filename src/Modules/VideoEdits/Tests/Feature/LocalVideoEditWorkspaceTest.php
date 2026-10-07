<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Modules\VideoEdits\Domain\Exceptions\InsufficientWorkspaceException;
use Modules\VideoEdits\Domain\Ports\VideoEditWorkspacePort;
use Modules\VideoEdits\Infrastructure\Media\LocalVideoEditWorkspace;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'video-edit-workspace-test-'.bin2hex(random_bytes(4));
    config()->set('filesystems.disks.video-edit-workspace.root', $this->root);
    $this->workspace = app(VideoEditWorkspacePort::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

it('prepares an empty directory per edit and wipes it afterwards', function (): void {
    $directory = $this->workspace->prepare('edit-uuid', 1);
    file_put_contents($this->workspace->path('edit-uuid', 'merged.mp4'), 'data');

    expect($directory)->toBe($this->root.DIRECTORY_SEPARATOR.'edit-uuid')
        ->and(is_file($directory.DIRECTORY_SEPARATOR.'merged.mp4'))->toBeTrue();

    $this->workspace->prepare('edit-uuid', 1);
    expect(is_file($directory.DIRECTORY_SEPARATOR.'merged.mp4'))->toBeFalse();

    $this->workspace->wipe('edit-uuid');
    expect(is_dir($directory))->toBeFalse();
});

it('never lets a file name escape the edit directory', function (): void {
    expect($this->workspace->path('edit-uuid', '../../etc/passwd'))
        ->toBe($this->root.DIRECTORY_SEPARATOR.'edit-uuid'.DIRECTORY_SEPARATOR.'passwd');
});

/**
 * A retry re-enters `prepare()` on a workspace the previous attempt could not
 * release. On Windows a file ffmpeg or an indexer still holds makes the cleanup
 * a no-op, the directory is still standing, and the bare `mkdir()` Laravel
 * calls used to raise `mkdir(): File exists` — promoted to an `ErrorException`
 * before any guard could see it — failing an edit that had already spent the
 * AI call. Reusing the directory is safe: producers write their own filenames.
 */
it('reuses a workspace it could not clear instead of failing the edit', function (): void {
    $files = new class extends Filesystem
    {
        /** Stands in for a file the OS still holds open. */
        public function deleteDirectory($directory, $preserve = false)
        {
            return false;
        }
    };

    $workspace = new LocalVideoEditWorkspace(
        app(Config::class),
        app(FilesystemFactory::class),
        $files,
    );

    $directory = $this->root.DIRECTORY_SEPARATOR.'edit-uuid';
    mkdir($directory, 0755, true);
    file_put_contents($directory.DIRECTORY_SEPARATOR.'merged.mp4', 'stale');

    expect($workspace->prepare('edit-uuid', 1))->toBe($directory)
        ->and(is_dir($directory))->toBeTrue();
});

it('refuses to start when the disk cannot hold the edit', function (): void {
    $this->workspace->prepare('edit-uuid', PHP_INT_MAX);
})->throws(InsufficientWorkspaceException::class);
