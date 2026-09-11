<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Enums\VideoEditStatus;

it('only allows the documented lifecycle transitions', function (VideoEditStatus $from, VideoEditStatus $to, bool $allowed): void {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'draft → queued' => [VideoEditStatus::Draft, VideoEditStatus::Queued, true],
    'draft → processing' => [VideoEditStatus::Draft, VideoEditStatus::Processing, false],
    'queued → processing' => [VideoEditStatus::Queued, VideoEditStatus::Processing, true],
    'queued → failed' => [VideoEditStatus::Queued, VideoEditStatus::Failed, true],
    'queued → completed' => [VideoEditStatus::Queued, VideoEditStatus::Completed, false],
    'processing → completed' => [VideoEditStatus::Processing, VideoEditStatus::Completed, true],
    'processing → failed' => [VideoEditStatus::Processing, VideoEditStatus::Failed, true],
    'processing → queued' => [VideoEditStatus::Processing, VideoEditStatus::Queued, false],
    'failed → queued (retry)' => [VideoEditStatus::Failed, VideoEditStatus::Queued, true],
    'failed → completed' => [VideoEditStatus::Failed, VideoEditStatus::Completed, false],
    'completed → queued' => [VideoEditStatus::Completed, VideoEditStatus::Queued, false],
]);

it('treats only queued and processing as active', function (): void {
    expect(VideoEditStatus::activeValues())->toBe(['queued', 'processing'])
        ->and(VideoEditStatus::Draft->isActive())->toBeFalse()
        ->and(VideoEditStatus::Completed->isActive())->toBeFalse()
        ->and(VideoEditStatus::Failed->isActive())->toBeFalse();
});

it('blocks deletion only while processing', function (VideoEditStatus $status, bool $deletable): void {
    expect($status->isDeletable())->toBe($deletable);
})->with([
    'draft' => [VideoEditStatus::Draft, true],
    'queued' => [VideoEditStatus::Queued, true],
    'processing' => [VideoEditStatus::Processing, false],
    'completed' => [VideoEditStatus::Completed, true],
    'failed' => [VideoEditStatus::Failed, true],
]);

it('never lists drafts in the history', function (): void {
    expect(VideoEditStatus::Draft->isListed())->toBeFalse()
        ->and(VideoEditStatus::Queued->isListed())->toBeTrue()
        ->and(VideoEditStatus::Completed->isListed())->toBeTrue();
});
