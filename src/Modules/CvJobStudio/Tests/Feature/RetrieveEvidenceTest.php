<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Application\Commands\StoreEmbeddingHandler;
use Modules\CvJobStudio\Application\Queries\RetrieveEvidenceHandler;
use Modules\CvJobStudio\Domain\Ports\EmbeddingPort;
use Modules\CvJobStudio\Domain\Ports\SimilaritySearchPort;
use Modules\CvJobStudio\Domain\Services\EvidenceRetriever;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvBulletEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvEntryEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvStructureEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function evidenceAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function evidenceEmbeddingFake(): EmbeddingPort
{
    return new class implements EmbeddingPort
    {
        public function embed(array $texts): array
        {
            // Constant unit vector: every text is equally similar, so the
            // wiring (embed → search → rank) is exercised, not the math.
            return [
                'vectors' => array_map(static fn (): array => array_fill(0, 8, 0.5), $texts),
                'model' => 'fake',
                'dims' => 8,
            ];
        }
    };
}

it('retrieves top bullets per requirement from stored vectors (T-123)', function (): void {
    $admin = evidenceAdmin();
    $fake = evidenceEmbeddingFake();
    $store = new StoreEmbeddingHandler($fake);

    $cv = CvEloquentModel::factory()->create(['user_id' => $admin->id, 'raw_text' => 'Laravel developer']);

    $structure = StudioCvStructureEloquentModel::query()->create([
        'user_id' => $admin->id,
        'cv_id' => $cv->id,
        'source_text_hash' => hash('sha256', 'Laravel developer'),
        'parser_version' => 'test',
        'parsed_at' => now(),
        'profile_facts' => [],
    ]);

    $entry = StudioCvEntryEloquentModel::query()->create([
        'user_id' => $admin->id,
        'structure_id' => $structure->id,
        'kind' => 'experience',
        'ordinal' => 0,
    ]);

    $bullet = StudioCvBulletEloquentModel::query()->create([
        'user_id' => $admin->id,
        'structure_id' => $structure->id,
        'entry_id' => $entry->id,
        'ordinal' => 0,
        'text' => 'Built Laravel APIs serving 25 clients.',
        'has_metric' => true,
        'xyz_complete' => true,
        'char_count' => 42,
    ]);

    (void) $store->handle('cv_bullet', $bullet->id, $bullet->text, $admin->id);

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

    $posting = StudioPostingEloquentModel::query()->create([
        'user_id' => $admin->id,
        'profile_id' => $profile->id,
        'canonical_url' => 'https://boards.greenhouse.io/acme/jobs/evidence-1',
        'url_hash' => hash('sha256', 'https://boards.greenhouse.io/acme/jobs/evidence-1'),
        'title' => 'Senior Fullstack Developer',
        'remote_scope' => 'remote_eu',
        'status' => 'new',
    ]);

    $posting->requirements()->create([
        'user_id' => $admin->id,
        'canonical_name' => 'Laravel',
        'raw_text' => 'Laravel',
        'tag' => 'required',
        'nature' => 'hard',
        'source_text_hash' => hash('sha256', 'Laravel'),
        'extracted_by_provider' => 'fake',
        'extracted_by_model' => 'fake-1',
        'prompt_version' => 'v2',
    ]);

    $result = (new RetrieveEvidenceHandler($fake, app(SimilaritySearchPort::class), new EvidenceRetriever))
        ->handle($posting->uuid, $structure->id, $admin->id);

    expect($result['evidence'])->toHaveKey('Laravel')
        ->and($result['evidence']['Laravel'][0]['bullet_id'])->toBe($bullet->id)
        ->and($result['evidence']['Laravel'][0]['text'])->toContain('Laravel APIs');
});

it('returns empty evidence when nothing is embedded yet', function (): void {
    $admin = evidenceAdmin();

    $cv = CvEloquentModel::factory()->create(['user_id' => $admin->id, 'raw_text' => 'Plain CV']);

    $structure = StudioCvStructureEloquentModel::query()->create([
        'user_id' => $admin->id,
        'cv_id' => $cv->id,
        'source_text_hash' => hash('sha256', 'Plain CV'),
        'parser_version' => 'test',
        'parsed_at' => now(),
        'profile_facts' => [],
    ]);

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

    $posting = StudioPostingEloquentModel::query()->create([
        'user_id' => $admin->id,
        'profile_id' => $profile->id,
        'canonical_url' => 'https://boards.greenhouse.io/acme/jobs/evidence-2',
        'url_hash' => hash('sha256', 'https://boards.greenhouse.io/acme/jobs/evidence-2'),
        'title' => 'Senior Fullstack Developer',
        'remote_scope' => 'remote_eu',
        'status' => 'new',
    ]);

    $result = (new RetrieveEvidenceHandler(evidenceEmbeddingFake(), app(SimilaritySearchPort::class), new EvidenceRetriever))
        ->handle($posting->uuid, $structure->id, $admin->id);

    expect($result)->toBe(['evidence' => [], 'proposals' => []]);
});
