<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Shared\Domain\Exceptions\StorageObjectNotFoundException;
use Shared\Infrastructure\Storage\R2StorageAdapter;

beforeEach(function (): void {
    Storage::fake('r2');
    $this->storage = new R2StorageAdapter('r2');
});

it('reports the byte size of a stored object without downloading it', function (): void {
    Storage::disk('r2')->put('video-edits/user/edit/sources/clip.mp4', str_repeat('x', 1_234));

    expect($this->storage->size('video-edits/user/edit/sources/clip.mp4'))->toBe(1_234)
        ->and($this->storage->size('/video-edits/user/edit/sources/clip.mp4'))->toBe(1_234);
});

it('tells a missing object apart from other storage failures', function (): void {
    $this->storage->size('video-edits/user/edit/sources/never-uploaded.mp4');
})->throws(StorageObjectNotFoundException::class);
