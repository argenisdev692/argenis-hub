<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;
use Modules\VideoEdits\Tests\Support\FakeStorage;
use Shared\Domain\Ports\StoragePort;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->storage = new FakeStorage;
    app()->instance(StoragePort::class, $this->storage);
});

function storedSourceFor(VideoEditEloquentModel $edit, FakeStorage $storage): VideoEditSourceEloquentModel
{
    $source = VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create();
    $storage->seed((string) $source->storage_path, 10);

    return $source;
}

it('purges sources of failed edits once the retry window has closed', function (): void {
    $expired = VideoEditEloquentModel::factory()->failedWithExpiredSources()->create();
    $expiredSource = storedSourceFor($expired, $this->storage);
    $stillRetryable = VideoEditEloquentModel::factory()->failed()->create();
    $retainedSource = storedSourceFor($stillRetryable, $this->storage);

    $this->artisan('video-edits:purge-sources')->assertSuccessful();

    expect($expiredSource->fresh()->storage_path)->toBeNull()
        ->and($expired->fresh()->sources_purged_at)->not->toBeNull()
        ->and($this->storage->exists((string) $expiredSource->getOriginal('storage_path')))->toBeFalse()
        ->and($retainedSource->fresh()->storage_path)->not->toBeNull()
        ->and($stillRetryable->fresh()->sources_purged_at)->toBeNull();
});

it('finishes deleting sources a completed edit could not delete at publish time', function (): void {
    $completed = VideoEditEloquentModel::factory()->completed()->create();
    $source = storedSourceFor($completed, $this->storage);

    $this->artisan('video-edits:purge-sources')->assertSuccessful();

    expect($source->fresh()->storage_path)->toBeNull()
        ->and($completed->fresh()->sources_purged_at)->not->toBeNull()
        ->and($this->storage->objects)->toBe([]);
});

it('keeps a path when its file cannot be deleted yet', function (): void {
    $expired = VideoEditEloquentModel::factory()->failedWithExpiredSources()->create();
    $source = VideoEditSourceEloquentModel::factory()->for($expired, 'videoEdit')->create();
    $storage = Mockery::mock(StoragePort::class);
    $storage->shouldReceive('delete')->andThrow(new RuntimeException('R2 unavailable'));
    app()->instance(StoragePort::class, $storage);

    $this->artisan('video-edits:purge-sources')->assertSuccessful();

    expect($source->fresh()->storage_path)->not->toBeNull()
        ->and($expired->fresh()->sources_purged_at)->toBeNull();
});

it('times out edits that stopped reporting progress', function (): void {
    $stuck = VideoEditEloquentModel::factory()->processing()->create(['updated_at' => now()->subMinutes(70)]);
    $alive = VideoEditEloquentModel::factory()->processing()->create(['updated_at' => now()->subMinutes(10)]);

    $this->artisan('video-edits:sweep')->assertSuccessful();

    expect($stuck->fresh()->status)->toBe(VideoEditStatus::Failed)
        ->and($stuck->fresh()->failure_code)->toBe('processing_timeout')
        ->and($stuck->fresh()->sources_expire_at?->isFuture())->toBeTrue()
        ->and($alive->fresh()->status)->toBe(VideoEditStatus::Processing);
});

it('deletes drafts that were never submitted, with anything uploaded for them', function (): void {
    $abandoned = VideoEditEloquentModel::factory()->create(['created_at' => now()->subHours(25)]);
    $uploaded = storedSourceFor($abandoned, $this->storage);
    $recent = VideoEditEloquentModel::factory()->create(['created_at' => now()->subHour()]);
    $queued = VideoEditEloquentModel::factory()->queued()->create(['created_at' => now()->subHours(30)]);

    $this->artisan('video-edits:sweep')->assertSuccessful();

    expect(VideoEditEloquentModel::query()->whereKey($abandoned->id)->exists())->toBeFalse()
        ->and($this->storage->deleted)->toBe([$uploaded->storage_path])
        ->and($recent->fresh())->not->toBeNull()
        ->and($queued->fresh()->status)->toBe(VideoEditStatus::Queued);
});

it('schedules both maintenance commands', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('video-edits:purge-sources')
        ->expectsOutputToContain('video-edits:sweep')
        ->assertSuccessful();
});
