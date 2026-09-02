<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('r2');

    $this->owner = User::factory()->create();
    $this->owner->assignRole('SUPER_ADMIN');

    $this->intruder = User::factory()->create();
    $this->intruder->assignRole('SUPER_ADMIN');

    $this->cv = CvEloquentModel::factory()->create(['user_id' => $this->owner->id]);
});

it('hides another user cv from the list', function (): void {
    $response = $this->actingAs($this->intruder)
        ->getJson('/cvs')
        ->assertOk();

    expect($response->json('data'))->toBeArray()->toBeEmpty();
});

it('returns 404 when another user cv is fetched by uuid', function (): void {
    $this->actingAs($this->intruder)
        ->getJson("/cvs/{$this->cv->uuid}")
        ->assertNotFound();
});

it('refuses to soft delete another user cv', function (): void {
    $this->actingAs($this->intruder)
        ->delete("/cvs/{$this->cv->uuid}")
        ->assertRedirect();

    $this->assertDatabaseHas('cvs', ['uuid' => $this->cv->uuid, 'deleted_at' => null]);
});

it('refuses to bulk delete another user cv', function (): void {
    $this->actingAs($this->intruder)
        ->post('/cvs/bulk-delete', ['uuids' => [$this->cv->uuid]])
        ->assertRedirect();

    $this->assertDatabaseHas('cvs', ['uuid' => $this->cv->uuid, 'deleted_at' => null]);
});

it('refuses to update another user cv', function (): void {
    $this->actingAs($this->intruder)
        ->put("/cvs/{$this->cv->uuid}", [
            'title' => 'Hijacked',
            'niche' => 'other',
            'is_primary' => false,
        ])
        ->assertNotFound();

    expect($this->cv->refresh()->title)->not->toBe('Hijacked');
});

it('never exposes raw_text, file_path or the internal id', function (): void {
    $payload = $this->actingAs($this->owner)
        ->getJson("/cvs/{$this->cv->uuid}")
        ->assertOk()
        ->json('data');

    expect($payload)->not->toHaveKeys(['raw_text', 'file_path', 'id', 'user_id'])
        ->and($payload['download_url'])->toBeString();
});

it('excludes another user cv from the export', function (): void {
    $csv = $this->actingAs($this->intruder)
        ->get('/cvs/export?format=csv')
        ->assertOk()
        ->streamedContent();

    expect($csv)->not->toContain($this->cv->title);
});

it('wires the cvs relation on both sides of the foreign key', function (): void {
    expect($this->owner->cvs)->toHaveCount(1)
        ->and($this->owner->cvs->first()->uuid)->toBe($this->cv->uuid)
        ->and($this->cv->user->id)->toBe($this->owner->id);
});

it('counts a user cvs without loading them', function (): void {
    CvEloquentModel::factory()->count(2)->create(['user_id' => $this->owner->id]);

    $user = User::query()->withCount('cvs')->findOrFail($this->owner->id);

    expect($user->cvs_count)->toBe(3)
        ->and($user->relationLoaded('cvs'))->toBeFalse();
});

it('cascades cv deletion when the owner row is removed', function (): void {
    $this->owner->forceDelete();

    $this->assertDatabaseMissing('cvs', ['uuid' => $this->cv->uuid]);
});
