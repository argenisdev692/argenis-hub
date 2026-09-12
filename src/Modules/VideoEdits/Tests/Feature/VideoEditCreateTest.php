<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Tests\Support\FakeStorage;
use Shared\Domain\Ports\StoragePort;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->storage = new FakeStorage;
    app()->instance(StoragePort::class, $this->storage);
});

function videoEditor(): User
{
    $user = User::factory()->create();
    $user->assignRole('SUPER_ADMIN');

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function autoEditPayload(array $overrides = []): array
{
    return array_replace([
        'mode' => 'auto_edit',
        'silence_removal' => ['enabled' => true, 'threshold_seconds' => null],
        'manual_ranges' => [['start_ms' => 12_400, 'end_ms' => 14_800, 'note' => '  retake  ']],
        'sources' => [
            ['position' => 2, 'file_name' => 'take-2.MOV', 'mime_type' => 'video/quicktime', 'size_bytes' => 400_000_000],
            ['position' => 1, 'file_name' => 'take-1.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 734_003_200],
        ],
    ], $overrides);
}

it('creates a draft with one upload target per source', function (): void {
    $user = videoEditor();

    $response = $this->actingAs($user)->postJson('/data/admin/video-edits', autoEditPayload());

    $response->assertCreated()
        ->assertJsonPath('edit.status', 'draft')
        ->assertJsonPath('edit.mode', 'auto_edit')
        ->assertJsonPath('edit.parameters.silence_removal.threshold_seconds', 1)
        ->assertJsonPath('edit.parameters.manual_ranges.0.note', 'retake')
        ->assertJsonPath('edit.sources.0.original_name', 'take-1.mp4')
        ->assertJsonPath('edit.sources.1.original_name', 'take-2.MOV')
        ->assertJsonPath('edit.can_download', false)
        ->assertJsonCount(2, 'uploads');

    $edit = VideoEditEloquentModel::query()->with('sources')->sole();
    $firstSource = $edit->sources->first();

    expect($edit->status)->toBe(VideoEditStatus::Draft)
        ->and($edit->user_id)->toBe($user->id)
        ->and($firstSource->storage_path)->toBe("video-edits/{$user->uuid}/{$edit->uuid}/sources/{$firstSource->uuid}.mp4")
        ->and($edit->sources->last()->extension)->toBe('mov')
        ->and($response->json('uploads.0.source_uuid'))->toBe($firstSource->uuid)
        ->and($response->json('uploads.0.upload_url'))->toContain($firstSource->storage_path)
        ->and($response->getContent())->not->toContain('storage_path')
        ->and($response->getContent())->not->toContain('"id"');
});

it('creates a merge draft without cut settings', function (): void {
    $this->actingAs(videoEditor())->postJson('/data/admin/video-edits', [
        'mode' => 'merge',
        'sources' => autoEditPayload()['sources'],
    ])->assertCreated()
        ->assertJsonPath('edit.mode', 'merge')
        ->assertJsonPath('edit.parameters.silence_removal.enabled', false)
        ->assertJsonPath('edit.parameters.manual_ranges', []);

    expect(VideoEditEloquentModel::query()->sole()->mode)->toBe(VideoEditMode::Merge);
});

it('rejects invalid requests before creating anything', function (array $overrides, string $errorKey): void {
    $this->actingAs(videoEditor())
        ->postJson('/data/admin/video-edits', autoEditPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorKey);

    expect(VideoEditEloquentModel::query()->count())->toBe(0);
})->with([
    // AI edit is selectable since V3, but it cannot run on the auto-edit
    // payload alone — it needs its own block and consent (AiEditRequestTest).
    'AI edit without its own block' => [['mode' => 'ai_edit'], 'ai_edit'],
    'unknown mode' => [['mode' => 'transcode'], 'mode'],
    'merge needs two clips' => [['mode' => 'merge', 'silence_removal' => null, 'manual_ranges' => [], 'sources' => [
        ['position' => 1, 'file_name' => 'a.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 10],
    ]], 'sources'],
    'too many clips' => [['sources' => array_map(
        static fn (int $position): array => ['position' => $position, 'file_name' => "t{$position}.mp4", 'mime_type' => 'video/mp4', 'size_bytes' => 10],
        range(1, 11),
    )], 'sources'],
    'unsupported extension' => [['sources' => [['position' => 1, 'file_name' => 'clip.avi', 'mime_type' => 'video/mp4', 'size_bytes' => 10]]], 'sources.0.file_name'],
    'unsupported mime type' => [['sources' => [['position' => 1, 'file_name' => 'clip.mp4', 'mime_type' => 'image/png', 'size_bytes' => 10]]], 'sources.0.mime_type'],
    'file over 2 GB' => [['sources' => [['position' => 1, 'file_name' => 'clip.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 2_147_483_649]]], 'sources.0.size_bytes'],
    'duplicate positions' => [['sources' => [
        ['position' => 1, 'file_name' => 'a.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 10],
        ['position' => 1, 'file_name' => 'b.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 10],
    ]], 'sources.0.position'],
    'threshold too short' => [['silence_removal' => ['enabled' => true, 'threshold_seconds' => 0.2]], 'silence_removal.threshold_seconds'],
    'threshold too long' => [['silence_removal' => ['enabled' => true, 'threshold_seconds' => 10.5]], 'silence_removal.threshold_seconds'],
    'range ends before it starts' => [['manual_ranges' => [['start_ms' => 5_000, 'end_ms' => 5_000]]], 'manual_ranges.0.end_ms'],
    'negative range start' => [['manual_ranges' => [['start_ms' => -1, 'end_ms' => 5_000]]], 'manual_ranges.0.start_ms'],
    'more than 500 ranges' => [['manual_ranges' => array_map(
        static fn (int $i): array => ['start_ms' => $i * 10, 'end_ms' => $i * 10 + 5],
        range(0, 500),
    )], 'manual_ranges'],
    'merge with ranges' => [['mode' => 'merge', 'silence_removal' => null], 'manual_ranges'],
    'auto edit with nothing to cut' => [['silence_removal' => ['enabled' => false], 'manual_ranges' => []], 'mode'],
]);

it('links a re-edit to one of the caller\'s own edits', function (): void {
    $user = videoEditor();
    $original = VideoEditEloquentModel::factory()->for($user)->completed()->create();

    $this->actingAs($user)
        ->postJson('/data/admin/video-edits', autoEditPayload(['previous_edit_uuid' => $original->uuid]))
        ->assertCreated()
        ->assertJsonPath('edit.previous_edit_uuid', $original->uuid);
});

it('hides another user\'s edit when used as a re-edit origin', function (): void {
    $stranger = VideoEditEloquentModel::factory()->completed()->create();

    $this->actingAs(videoEditor())
        ->postJson('/data/admin/video-edits', autoEditPayload(['previous_edit_uuid' => $stranger->uuid]))
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');

    expect(VideoEditEloquentModel::query()->count())->toBe(1);
});

it('requires authentication and the create permission', function (): void {
    $this->postJson('/data/admin/video-edits', autoEditPayload())->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson('/data/admin/video-edits', autoEditPayload())
        ->assertForbidden();
});
