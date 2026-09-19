<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvBulletEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvEntryEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvSkillEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvStructureEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Shared\Infrastructure\AI\AIClientInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function tailorAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function tailorPosting(User $admin): StudioPostingEloquentModel
{
    $profile = StudioProfileEloquentModel::query()->create([
        'user_id' => $admin->id,
        'name' => 'Fullstack',
        'slug' => 'fullstack-'.Str::lower(Str::random(6)),
        'flow' => 'fullstack',
        'is_active' => true,
        'accepted_remote_scopes' => ['remote_eu'],
        'stack_must' => ['Laravel'],
        'stack_reject' => ['WordPress'],
        'rules' => config('cv-job-studio'),
        'rules_version' => 2,
    ]);

    return StudioPostingEloquentModel::query()->create([
        'user_id' => $admin->id,
        'profile_id' => $profile->id,
        'canonical_url' => 'https://boards.greenhouse.io/acme/jobs/tailor-1',
        'url_hash' => hash('sha256', 'https://boards.greenhouse.io/acme/jobs/tailor-1'),
        'title' => 'Senior Fullstack Developer',
        'remote_scope' => 'remote_eu',
        'status' => 'new',
    ]);
}

function tailorStructure(User $admin, bool $confirmed = true): StudioCvStructureEloquentModel
{
    $cv = CvEloquentModel::factory()->create(['user_id' => $admin->id, 'raw_text' => 'Laravel developer']);

    $structure = StudioCvStructureEloquentModel::query()->create([
        'user_id' => $admin->id,
        'cv_id' => $cv->id,
        'source_text_hash' => hash('sha256', 'Laravel developer'),
        'parser_version' => 'test',
        'parsed_at' => now(),
        'confirmed_at' => $confirmed ? now() : null,
        'profile_facts' => [],
    ]);

    $entry = StudioCvEntryEloquentModel::query()->create([
        'user_id' => $admin->id,
        'structure_id' => $structure->id,
        'kind' => 'experience',
        'ordinal' => 0,
    ]);

    StudioCvBulletEloquentModel::query()->create([
        'user_id' => $admin->id,
        'structure_id' => $structure->id,
        'entry_id' => $entry->id,
        'ordinal' => 0,
        'text' => 'Built Laravel APIs.',
        'has_metric' => false,
        'xyz_complete' => false,
        'char_count' => 19,
    ]);

    StudioCvSkillEloquentModel::query()->create([
        'user_id' => $admin->id,
        'structure_id' => $structure->id,
        'canonical_name' => 'Laravel',
    ]);

    return $structure;
}

function tailorAiFake(): void
{
    $response = Mockery::mock(StructuredAgentResponse::class);
    $response->shouldReceive('offsetExists')->andReturn(true);
    $response->shouldReceive('offsetGet')->with('summary')->andReturn('Senior Laravel developer.');
    $response->shouldReceive('offsetGet')->with('skills')->andReturn(['Laravel']);
    $response->shouldReceive('offsetGet')->with('provenance')->andReturn([]);

    $ai = Mockery::mock(AIClientInterface::class);
    $ai->shouldReceive('generateStructured')->andReturn($response);
    app()->instance(AIClientInterface::class, $ai);
}

it('tailors a posting from chat notes through the endpoint', function (): void {
    $admin = tailorAdmin();
    $posting = tailorPosting($admin);
    $structure = tailorStructure($admin);
    tailorAiFake();

    $uuid = $this->actingAs($admin)
        ->postJson("/cv-studio/postings/{$posting->uuid}/tailor", [
            'structure_uuid' => $structure->uuid,
            'language' => 'en',
            'notes' => 'Emphasize API work.',
        ])
        ->assertCreated()
        ->json('data.uuid');

    $version = StudioCvVersionEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

    expect($version->purpose)->toBe('tailored')
        ->and($version->language)->toBe('en')
        ->and((int) $version->cv_id)->toBe($structure->cv_id)
        ->and((int) $version->structure_id)->toBe($structure->id)
        ->and((int) $version->posting_id)->toBe($posting->id);
});

it('rejects oversized notes and unknown languages', function (): void {
    $admin = tailorAdmin();
    $posting = tailorPosting($admin);
    $structure = tailorStructure($admin);

    $this->actingAs($admin)
        ->postJson("/cv-studio/postings/{$posting->uuid}/tailor", [
            'structure_uuid' => $structure->uuid,
            'language' => 'en',
            'notes' => str_repeat('x', 2001),
        ])
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->postJson("/cv-studio/postings/{$posting->uuid}/tailor", [
            'structure_uuid' => $structure->uuid,
            'language' => 'xx',
        ])
        ->assertUnprocessable();
});

it('refuses an unconfirmed structure', function (): void {
    $admin = tailorAdmin();
    $posting = tailorPosting($admin);
    $structure = tailorStructure($admin, confirmed: false);
    tailorAiFake();

    $this->actingAs($admin)
        ->postJson("/cv-studio/postings/{$posting->uuid}/tailor", [
            'structure_uuid' => $structure->uuid,
            'language' => 'en',
        ])
        ->assertUnprocessable();
});
