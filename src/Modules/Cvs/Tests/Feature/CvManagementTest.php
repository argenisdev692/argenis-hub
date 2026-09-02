<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Cvs\Domain\Enums\CvFileType;
use Modules\Cvs\Domain\Enums\CvNiche;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('r2');
});

function superAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('uploads a markdown cv', function (): void {
    $admin = superAdmin();
    $file = UploadedFile::fake()->createWithContent('resume.md', "# Fullstack Resume\n\nLaravel + Vue");

    $this->actingAs($admin)
        ->post('/cvs', [
            'title' => 'My Fullstack CV',
            'niche' => 'fullstack',
            'is_primary' => true,
            'file' => $file,
        ])
        ->assertRedirect();

    $cv = CvEloquentModel::query()->where('title', 'My Fullstack CV')->firstOrFail();

    expect($cv->user_id)->toBe($admin->id)
        ->and($cv->niche)->toBe(CvNiche::Fullstack)
        ->and($cv->is_primary)->toBeTrue()
        ->and($cv->file_type)->toBe(CvFileType::Md)
        ->and($cv->raw_text)->toContain('Fullstack Resume');
});

it('rejects a create without a file', function (): void {
    $this->actingAs(superAdmin())
        ->post('/cvs', [
            'title' => 'Missing file',
            'niche' => 'fullstack',
            'is_primary' => false,
        ])
        ->assertSessionHasErrors('file');
});

it('rejects an unknown niche', function (): void {
    $this->actingAs(superAdmin())
        ->post('/cvs', [
            'title' => 'Bad niche',
            'niche' => 'marketing',
            'is_primary' => false,
            'file' => UploadedFile::fake()->createWithContent('resume.md', '# CV'),
        ])
        ->assertSessionHasErrors('niche');
});

it('rejects a file whose extension is not pdf or markdown', function (): void {
    $this->actingAs(superAdmin())
        ->post('/cvs', [
            'title' => 'Executable',
            'niche' => 'fullstack',
            'is_primary' => false,
            'file' => UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;'),
        ])
        ->assertSessionHasErrors('file');
});

it('clears the previous primary when a new primary is uploaded', function (): void {
    $admin = superAdmin();
    $existing = CvEloquentModel::factory()->primary()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->post('/cvs', [
            'title' => 'New primary CV',
            'niche' => 'fullstack',
            'is_primary' => true,
            'file' => UploadedFile::fake()->createWithContent('new.md', '# New primary'),
        ])
        ->assertRedirect();

    expect($existing->refresh()->is_primary)->toBeFalse()
        ->and(CvEloquentModel::query()->where('title', 'New primary CV')->value('is_primary'))->toBeTrue();
});

it('leaves another user primary cv untouched', function (): void {
    $admin = superAdmin();
    $otherPrimary = CvEloquentModel::factory()->primary()->create();

    $this->actingAs($admin)
        ->post('/cvs', [
            'title' => 'Mine',
            'niche' => 'fullstack',
            'is_primary' => true,
            'file' => UploadedFile::fake()->createWithContent('mine.md', '# Mine'),
        ])
        ->assertRedirect();

    expect($otherPrimary->refresh()->is_primary)->toBeTrue();
});

it('deletes then restores a cv', function (): void {
    $admin = superAdmin();
    $cv = CvEloquentModel::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->delete("/cvs/{$cv->uuid}")->assertRedirect();
    $this->assertSoftDeleted('cvs', ['uuid' => $cv->uuid]);

    $this->actingAs($admin)->patch("/cvs/{$cv->uuid}/restore")->assertRedirect();
    $this->assertDatabaseHas('cvs', ['uuid' => $cv->uuid, 'deleted_at' => null]);
});

it('bulk deletes then bulk restores', function (): void {
    $admin = superAdmin();
    $uuids = CvEloquentModel::factory()->count(3)->create(['user_id' => $admin->id])->pluck('uuid')->all();

    $this->actingAs($admin)->post('/cvs/bulk-delete', ['uuids' => $uuids])->assertRedirect();
    foreach ($uuids as $uuid) {
        $this->assertSoftDeleted('cvs', ['uuid' => $uuid]);
    }

    $this->actingAs($admin)->post('/cvs/bulk-restore', ['uuids' => $uuids])->assertRedirect();
    foreach ($uuids as $uuid) {
        $this->assertDatabaseHas('cvs', ['uuid' => $uuid, 'deleted_at' => null]);
    }
});

it('caps a bulk payload at 500 uuids', function (): void {
    $this->actingAs(superAdmin())
        ->post('/cvs/bulk-delete', ['uuids' => array_map(
            static fn (): string => (string) Str::uuid7(),
            range(1, 501),
        )])
        ->assertSessionHasErrors('uuids');
});

it('redirects a guest to login', function (): void {
    $this->get('/cvs')->assertRedirect('/login');
});

/**
 * Asserted against the JSON branch, not the Inertia one: `cvs/Index.vue` has
 * not been built yet, so `Inertia::render()` would fail on the Vite manifest
 * rather than on anything this test is about — which is that the ADMIN role
 * clears `permission:VIEW_ANY_CVS`.
 */
it('lets the admin role list cvs', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('ADMIN');

    $this->actingAs($admin)->getJson('/cvs')->assertOk();
});

it('denies a user without the cvs permissions', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/cvs')
        ->assertForbidden();
});

it('streams a csv export', function (): void {
    $admin = superAdmin();
    CvEloquentModel::factory()->create(['user_id' => $admin->id, 'title' => 'Export Me']);

    $this->actingAs($admin)->get('/cvs/export?format=csv')->assertOk();
});

it('rejects an unsupported export format', function (): void {
    $this->actingAs(superAdmin())->get('/cvs/export?format=exe')->assertStatus(422);
});
