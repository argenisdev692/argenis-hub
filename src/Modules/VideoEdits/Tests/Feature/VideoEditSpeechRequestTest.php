<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Domain\Enums\SpeechCategory;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function speechPayload(array $overrides = []): array
{
    return array_replace([
        'mode' => 'auto_edit',
        'speech_cleanup' => ['enabled' => true],
        'sources' => [
            ['position' => 1, 'file_name' => 'take-1.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 10_000_000],
        ],
    ], $overrides);
}

it('accepts speech cleanup as the only thing to cut', function (): void {
    // The V1 rule was "silence or ranges"; enabling Whisper is now reason
    // enough for an auto edit to be worth running.
    $this->actingAs(VideoEditTestUsers::editor())
        ->postJson('/data/admin/video-edits', speechPayload())
        ->assertCreated();
});

it('stores every category when none are named', function (): void {
    $this->actingAs(VideoEditTestUsers::editor())
        ->postJson('/data/admin/video-edits', speechPayload())
        ->assertCreated();

    $edit = VideoEditEloquentModel::query()->latest('id')->firstOrFail();

    expect($edit->parameters['speech_cleanup']['enabled'])->toBeTrue()
        ->and($edit->parameters['speech_cleanup']['categories'])->toBe(SpeechCategory::values())
        ->and($edit->parameters['speech_cleanup']['language'])->toBe('es');
});

it('stores only the categories the user chose', function (): void {
    $this->actingAs(VideoEditTestUsers::editor())
        ->postJson('/data/admin/video-edits', speechPayload([
            'speech_cleanup' => ['enabled' => true, 'categories' => ['filler', 'repetition'], 'language' => 'en'],
        ]))
        ->assertCreated();

    $edit = VideoEditEloquentModel::query()->latest('id')->firstOrFail();

    expect($edit->parameters['speech_cleanup']['categories'])->toBe(['filler', 'repetition'])
        ->and($edit->parameters['speech_cleanup']['language'])->toBe('en');
});

it('rejects an unknown detection category', function (): void {
    $this->actingAs(VideoEditTestUsers::editor())
        ->postJson('/data/admin/video-edits', speechPayload([
            'speech_cleanup' => ['enabled' => true, 'categories' => ['drop_everything']],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('speech_cleanup.categories.0');
});

it('rejects speech cleanup on a merge, which does no content analysis', function (): void {
    $this->actingAs(VideoEditTestUsers::editor())
        ->postJson('/data/admin/video-edits', speechPayload([
            'mode' => 'merge',
            'sources' => [
                ['position' => 1, 'file_name' => 'a.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 1_000],
                ['position' => 2, 'file_name' => 'b.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 1_000],
            ],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('speech_cleanup');
});

it('still rejects an auto edit with nothing at all to cut', function (): void {
    $this->actingAs(VideoEditTestUsers::editor())
        ->postJson('/data/admin/video-edits', speechPayload([
            'speech_cleanup' => ['enabled' => false],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('mode');
});

it('records speech cleanup as off when it was never asked for', function (): void {
    $this->actingAs(VideoEditTestUsers::editor())
        ->postJson('/data/admin/video-edits', speechPayload([
            'speech_cleanup' => null,
            'silence_removal' => ['enabled' => true, 'threshold_seconds' => null],
        ]))
        ->assertCreated();

    $edit = VideoEditEloquentModel::query()->latest('id')->firstOrFail();

    expect($edit->parameters['speech_cleanup']['enabled'])->toBeFalse()
        ->and($edit->parameters['speech_cleanup']['categories'])->toBe([]);
});
