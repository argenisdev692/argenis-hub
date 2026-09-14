<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('r2');

    $this->owner = User::factory()->create();
    $this->owner->assignRole('SUPER_ADMIN');
});

it('streams an xlsx export', function (): void {
    CvEloquentModel::factory()->create(['user_id' => $this->owner->id]);

    $disposition = $this->actingAs($this->owner)
        ->get('/cvs/export?format=xlsx')
        ->assertOk()
        ->headers->get('content-disposition');

    expect($disposition)->toStartWith('attachment;')->toEndWith('cvs.xlsx');
});

it('renders a pdf export', function (): void {
    CvEloquentModel::factory()->create(['user_id' => $this->owner->id]);

    $this->actingAs($this->owner)
        ->get('/cvs/export?format=pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('exports only suspended cvs when status is suspended', function (): void {
    CvEloquentModel::factory()->create(['user_id' => $this->owner->id, 'title' => 'Live CV']);
    CvEloquentModel::factory()->create(['user_id' => $this->owner->id, 'title' => 'Archived CV'])->delete();

    $csv = $this->actingAs($this->owner)
        ->get('/cvs/export?format=csv&status=suspended')
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Archived CV')->toContain('Suspended')
        ->and($csv)->not->toContain('Live CV');
});

it('lists active and suspended cvs together when status is all', function (): void {
    CvEloquentModel::factory()->create(['user_id' => $this->owner->id, 'title' => 'Live CV']);
    CvEloquentModel::factory()->create(['user_id' => $this->owner->id, 'title' => 'Archived CV'])->delete();

    $this->actingAs($this->owner)
        ->getJson('/cvs?status=all')
        ->assertOk()
        ->assertJsonPath('total', 2);

    $this->actingAs($this->owner)
        ->getJson('/cvs?status=active')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Live CV');
});

it('no longer registers a post alias for the update route', function (): void {
    expect(Route::has('cvs.update.post'))->toBeFalse()
        ->and(Route::has('cvs.update'))->toBeTrue();
});

it('rejects an inverted date range', function (): void {
    $this->actingAs($this->owner)
        ->getJson('/cvs?date_from=2026-09-10&date_to=2026-09-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_from');
});

it('replaces the stored file on update and deletes the previous object', function (): void {
    Storage::disk('r2')->put('cvs/old.md', '# Old');
    $cv = CvEloquentModel::factory()->markdown()->create([
        'user_id' => $this->owner->id,
        'file_path' => 'cvs/old.md',
    ]);

    $this->actingAs($this->owner)
        ->post("/cvs/{$cv->uuid}", [
            '_method' => 'PUT',
            'title' => 'Renamed',
            'niche' => 'other',
            'is_primary' => false,
            'file' => UploadedFile::fake()->createWithContent('new.md', '# Brand new'),
        ])
        ->assertRedirect();

    $cv->refresh();

    expect($cv->title)->toBe('Renamed')
        ->and($cv->raw_text)->toContain('Brand new')
        ->and($cv->file_path)->not->toBe('cvs/old.md');

    Storage::disk('r2')->assertMissing('cvs/old.md');
    Storage::disk('r2')->assertExists($cv->file_path);
});

it('removes the uploaded object when the database write fails', function (): void {
    $this->mock(CvRepositoryPort::class)
        ->shouldReceive('create')
        ->once()
        ->andThrow(new RuntimeException('database unavailable'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->owner)->post('/cvs', [
        'title' => 'Doomed',
        'niche' => 'fullstack',
        'is_primary' => false,
        'file' => UploadedFile::fake()->createWithContent('doomed.md', '# Doomed'),
    ]))->toThrow(RuntimeException::class, 'database unavailable');

    expect(Storage::disk('r2')->allFiles('cvs'))->toBeEmpty();
});

it('records single and bulk deletes and restores in the activity log', function (): void {
    $single = CvEloquentModel::factory()->create(['user_id' => $this->owner->id]);
    $bulk = CvEloquentModel::factory()->count(2)->create(['user_id' => $this->owner->id]);

    $this->actingAs($this->owner)->delete("/cvs/{$single->uuid}")->assertRedirect();
    $this->actingAs($this->owner)->patch("/cvs/{$single->uuid}/restore")->assertRedirect();
    $this->actingAs($this->owner)->post('/cvs/bulk-delete', ['uuids' => $bulk->pluck('uuid')->all()])->assertRedirect();
    $this->actingAs($this->owner)->post('/cvs/bulk-restore', ['uuids' => $bulk->pluck('uuid')->all()])->assertRedirect();

    $events = fn (string $event): int => Activity::query()
        ->where('log_name', 'cvs')
        ->where('event', $event)
        ->count();

    expect($events('deleted'))->toBe(3)
        ->and($events('restored'))->toBe(3);
});

it('lists and shows own cvs through the sanctum api', function (): void {
    $cv = CvEloquentModel::factory()->create(['user_id' => $this->owner->id]);
    CvEloquentModel::factory()->create();

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/cvs')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $cv->uuid);

    $this->getJson("/api/cvs/{$cv->uuid}")
        ->assertOk()
        ->assertJsonPath('data.uuid', $cv->uuid)
        ->assertJsonMissingPath('data.raw_text');
});

it('returns 404 from the api for another user cv', function (): void {
    $foreign = CvEloquentModel::factory()->create();

    Sanctum::actingAs($this->owner);

    $this->getJson("/api/cvs/{$foreign->uuid}")->assertNotFound();
});
