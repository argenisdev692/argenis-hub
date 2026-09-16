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
    'processing → awaiting review' => [VideoEditStatus::Processing, VideoEditStatus::AwaitingReview, true],
    'awaiting review → queued (reviewed)' => [VideoEditStatus::AwaitingReview, VideoEditStatus::Queued, true],
    'awaiting review → completed' => [VideoEditStatus::AwaitingReview, VideoEditStatus::Completed, false],
    'awaiting review → processing' => [VideoEditStatus::AwaitingReview, VideoEditStatus::Processing, false],
    'queued → awaiting review' => [VideoEditStatus::Queued, VideoEditStatus::AwaitingReview, false],
    'failed → queued (retry)' => [VideoEditStatus::Failed, VideoEditStatus::Queued, true],
    'failed → completed' => [VideoEditStatus::Failed, VideoEditStatus::Completed, false],
    'completed → queued' => [VideoEditStatus::Completed, VideoEditStatus::Queued, false],
]);

it('treats queued, processing and awaiting review as active', function (): void {
    expect(VideoEditStatus::activeValues())->toBe(['queued', 'processing', 'awaiting_review'])
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
    'awaiting review' => [VideoEditStatus::AwaitingReview, true],
    'completed' => [VideoEditStatus::Completed, true],
    'failed' => [VideoEditStatus::Failed, true],
]);

it('never lists drafts in the history', function (): void {
    expect(VideoEditStatus::Draft->isListed())->toBeFalse()
        ->and(VideoEditStatus::Queued->isListed())->toBeTrue()
        ->and(VideoEditStatus::Completed->isListed())->toBeTrue();
});
