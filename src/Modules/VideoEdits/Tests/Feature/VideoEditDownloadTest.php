<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Tests\Support\FakeStorage;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;
use Shared\Domain\Ports\StoragePort;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->storage = new FakeStorage;
    app()->instance(StoragePort::class, $this->storage);
});

it('issues a 15-minute signed link to the finished video', function (): void {
    Carbon::setTestNow('2026-09-11 12:00:00');
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->completed()->create();

    $response = $this->actingAs($user)->getJson("/data/admin/video-edits/{$edit->uuid}/download-url");

    $response->assertOk()
        ->assertJsonPath('expires_at', '2026-09-11T12:15:00+00:00');

    expect($response->json('url'))->toContain($edit->result_path)
        ->and($response->json('url'))->toContain('signature=download');
});

it('refuses to link a video that is not finished', function (string $state): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->{$state}()->create();

    $this->actingAs($user)
        ->getJson("/data/admin/video-edits/{$edit->uuid}/download-url")
        ->assertConflict()
        ->assertJsonPath('code', 'not_completed');
})->with(['queued', 'processing', 'failed']);

it('hides other users\' results', function (): void {
    $edit = VideoEditEloquentModel::factory()->completed()->create();

    $this->actingAs(VideoEditTestUsers::editor())
        ->getJson("/data/admin/video-edits/{$edit->uuid}/download-url")
        ->assertNotFound();
});
