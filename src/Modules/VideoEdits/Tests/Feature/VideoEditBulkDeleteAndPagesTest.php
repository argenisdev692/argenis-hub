<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Tests\Support\FakeStorage;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;
use Shared\Domain\Ports\StoragePort;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->storage = new FakeStorage;
    app()->instance(StoragePort::class, $this->storage);
});

it('bulk deletes the caller\'s edits and skips processing or foreign ones', function (): void {
    $user = VideoEditTestUsers::editor();
    $completed = VideoEditEloquentModel::factory()->for($user)->completed()->create();
    $failed = VideoEditEloquentModel::factory()->for($user)->failed()->create();
    $processing = VideoEditEloquentModel::factory()->for($user)->processing()->create();
    $foreign = VideoEditEloquentModel::factory()->completed()->create();

    $this->actingAs($user)
        ->postJson('/data/admin/video-edits/bulk-delete', [
            'uuids' => [$completed->uuid, $failed->uuid, $processing->uuid, $foreign->uuid],
        ])
        ->assertOk()
        ->assertExactJson(['deleted' => 2, 'skipped' => 2]);

    expect(VideoEditEloquentModel::query()->pluck('uuid')->all())
        ->toEqualCanonicalizing([$processing->uuid, $foreign->uuid])
        ->and(Activity::query()->where('description', 'video_edit.deleted')->count())->toBe(2);
});

it('validates the bulk delete selection', function (string $case): void {
    $payload = match ($case) {
        'missing' => [],
        'empty' => ['uuids' => []],
        'too many' => ['uuids' => array_map(static fn (): string => (string) Str::uuid(), range(1, 101))],
    };

    $this->actingAs(VideoEditTestUsers::editor())
        ->postJson('/data/admin/video-edits/bulk-delete', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('uuids');
})->with(['missing', 'empty', 'too many']);

it('renders the history page shell', function (): void {
    $this->withoutVite()
        ->actingAs(VideoEditTestUsers::editor())
        ->get('/video-edits')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('video-edits/Index', false));
});

it('renders the detail page shell for an owned edit only', function (): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->completed()->create();
    $foreign = VideoEditEloquentModel::factory()->completed()->create();

    $this->withoutVite()
        ->actingAs($user)
        ->get("/video-edits/{$edit->uuid}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('video-edits/Show', false)
            ->where('uuid', $edit->uuid));

    $this->actingAs($user)->get("/video-edits/{$foreign->uuid}")->assertNotFound();
});

it('forbids the pages without video edit permissions', function (string $uri): void {
    $this->actingAs(VideoEditTestUsers::withoutVideoEditPermissions())
        ->get($uri)
        ->assertForbidden();
})->with(['/video-edits', '/video-edits/0191e3a4-7c2b-7d3e-9f10-1234567890ab']);
