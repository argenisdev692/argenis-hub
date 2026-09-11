<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\VideoEdits\Domain\Exceptions\InsufficientWorkspaceException;
use Modules\VideoEdits\Domain\Ports\VideoEditWorkspacePort;

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

it('refuses to start when the disk cannot hold the edit', function (): void {
    $this->workspace->prepare('edit-uuid', PHP_INT_MAX);
})->throws(InsufficientWorkspaceException::class);
