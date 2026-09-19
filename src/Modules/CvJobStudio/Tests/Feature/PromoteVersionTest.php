<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\CvJobStudio\Application\Commands\ParseCvStructureHandler;
use Modules\CvJobStudio\Application\Commands\PromoteVersionToCvsHandler;
use Modules\CvJobStudio\Application\Commands\StoreEmbeddingHandler;
use Modules\CvJobStudio\Domain\Ports\CvStructureParserPort;
use Modules\CvJobStudio\Domain\Ports\EmbeddingPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvStructureEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;
use Modules\Cvs\Domain\Enums\CvSource;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('r2');
});

function promoteAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function promoteHandler(): PromoteVersionToCvsHandler
{
    $parser = new class implements CvStructureParserPort
    {
        public function parse(string $rawText, int $userId): array
        {
            return [
                'profile_facts' => [],
                'entries' => [['kind' => 'experience', 'organization' => 'Acme']],
                'bullets' => [['entry_ordinal' => 0, 'text' => 'Built Laravel APIs.']],
                'skills' => [['canonical_name' => 'Laravel']],
                'provider' => 'fake',
                'model' => 'fake-1',
                'parser_version' => 'test',
            ];
        }
    };

    $embeddings = new class implements EmbeddingPort
    {
        public function embed(array $texts): array
        {
            return [
                'vectors' => array_map(static fn (): array => array_fill(0, 8, 0.5), $texts),
                'model' => 'fake',
                'dims' => 8,
            ];
        }
    };

    $parse = app(ParseCvStructureHandler::class, [
        'parser' => $parser,
        'embeddings' => new StoreEmbeddingHandler($embeddings),
    ]);

    return app(PromoteVersionToCvsHandler::class, ['parse' => $parse]);
}

function promoteVersion(User $admin, CvEloquentModel $cv): StudioCvVersionEloquentModel
{
    return StudioCvVersionEloquentModel::query()->create([
        'user_id' => $admin->id,
        'cv_id' => $cv->id,
        'purpose' => 'ats_rewrite',
        'language' => 'en',
        'content' => ['sections' => [
            ['heading' => 'Experience', 'bullets' => [['text' => 'Built Laravel APIs.']]],
            ['heading' => 'Skills', 'bullets' => ['Laravel', 'Vue.js']],
        ]],
        'rules_version' => 2,
    ]);
}

it('promotes a version to a new primary cv without touching the source row', function (): void {
    $admin = promoteAdmin();
    $source = CvEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'is_primary' => true,
        'raw_text' => 'Original CV',
    ]);
    $version = promoteVersion($admin, $source);

    $cv = promoteHandler()->handle($version->uuid, $admin->id);

    expect($cv->is_primary)->toBeTrue()
        ->and($cv->source)->toBe(CvSource::Studio)
        ->and($cv->language)->toBe('en')
        ->and($cv->parent_cv_uuid)->toBe($source->uuid)
        ->and($cv->studio_version_uuid)->toBe($version->uuid)
        ->and($cv->raw_text)->toContain('## Experience')
        ->and($cv->raw_text)->toContain('Built Laravel APIs.')
        ->and($source->refresh()->is_primary)->toBeFalse()
        ->and($source->refresh()->raw_text)->toBe('Original CV');

    Storage::disk('r2')->assertExists($cv->file_path);

    // Re-parsed immediately so RAG consumers see the promoted CV.
    expect(StudioCvStructureEloquentModel::query()
        ->where('user_id', $admin->id)
        ->where('cv_id', $cv->id)
        ->exists())->toBeTrue();
});

it('promotes through the http confirmation endpoint', function (): void {
    $admin = promoteAdmin();
    $source = CvEloquentModel::factory()->create([
        'user_id' => $admin->id,
        'is_primary' => true,
        'raw_text' => 'Original CV',
    ]);
    $version = promoteVersion($admin, $source);

    $uuid = $this->actingAs($admin)
        ->postJson("/cv-studio/versions/{$version->uuid}/promote", ['title' => 'My EN CV'])
        ->assertCreated()
        ->json('data.uuid');

    $cv = CvEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

    expect($cv->title)->toBe('My EN CV')->and($cv->is_primary)->toBeTrue();
});

it('returns 404 for an unknown version', function (): void {
    $admin = promoteAdmin();

    $this->actingAs($admin)
        ->postJson('/cv-studio/versions/00000000-0000-0000-0000-000000000000/promote')
        ->assertNotFound();
});
