<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Application\Commands\ExpirePostingsHandler;
use Modules\CvJobStudio\Application\Commands\ExtractRequirementsHandler;
use Modules\CvJobStudio\Application\Commands\RefreshApplyPriorityHandler;
use Modules\CvJobStudio\Application\Commands\RefreshVocabularyHandler;
use Modules\CvJobStudio\Application\Commands\RescorePostingHandler;
use Modules\CvJobStudio\Application\Commands\StoreEmbeddingHandler;
use Modules\CvJobStudio\Application\Queries\GetOwnRatesHandler;
use Modules\CvJobStudio\Domain\Ports\EmbeddingPort;
use Modules\CvJobStudio\Domain\Ports\RequirementExtractorPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioApplicationEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioEmbeddingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioVocabularyEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function coverageAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function coverageProfile(User $admin): StudioProfileEloquentModel
{
    return StudioProfileEloquentModel::query()->create([
        'user_id' => $admin->id,
        'name' => 'Fullstack',
        'slug' => 'fullstack-'.Str::lower(Str::random(6)),
        'flow' => 'fullstack',
        'is_active' => true,
        'accepted_remote_scopes' => ['remote_eu'],
        'stack_must' => ['Laravel', 'Vue.js'],
        'stack_reject' => ['WordPress'],
        'rules' => config('cv-job-studio'),
        'rules_version' => 2,
    ]);
}

function coveragePosting(User $admin, StudioProfileEloquentModel $profile, string $suffix): StudioPostingEloquentModel
{
    return StudioPostingEloquentModel::query()->create([
        'user_id' => $admin->id,
        'profile_id' => $profile->id,
        'canonical_url' => "https://boards.greenhouse.io/acme/jobs/{$suffix}",
        'url_hash' => hash('sha256', "https://boards.greenhouse.io/acme/jobs/{$suffix}"),
        'title' => 'Senior Fullstack Developer',
        'remote_scope' => 'remote_eu',
        'status' => 'new',
    ]);
}

it('extracts requirements once per text hash (T-057)', function (): void {
    $admin = coverageAdmin();
    $profile = coverageProfile($admin);
    $posting = coveragePosting($admin, $profile, 'extract-1');

    $posting->texts()->create([
        'user_id' => $admin->id,
        'ladder_step' => 'manual',
        'completeness' => 'full',
        'text' => 'We need Laravel and Vue.js experience. Benefits: free coffee.',
        'char_count' => 60,
    ]);

    $fake = new class implements RequirementExtractorPort
    {
        public int $calls = 0;

        public function extract(string $postingText, int $userId): array
        {
            $this->calls++;

            return [
                'requirements' => [
                    ['canonical_name' => 'Laravel', 'raw_text' => 'Laravel', 'tag' => 'required', 'nature' => 'hard'],
                    ['canonical_name' => 'Bogus', 'raw_text' => 'x', 'tag' => 'weird', 'nature' => 'hard'],
                ],
                'responsibilities' => [],
                'provider' => 'fake',
                'model' => 'fake-1',
                'prompt_version' => 'v2',
            ];
        }
    };

    $handler = app(ExtractRequirementsHandler::class, ['extractor' => $fake]);

    $first = $handler->handle($posting->uuid, $admin->id);
    $second = $handler->handle($posting->uuid, $admin->id);

    // Invalid enums rejected on the way out; second call hits the hash cache.
    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(1)
        ->and($fake->calls)->toBe(1)
        ->and($first[0]->extracted_by_provider)->toBe('fake');
});

it('writes embeddings once per content hash (T-053)', function (): void {
    $admin = coverageAdmin();

    $fake = new class implements EmbeddingPort
    {
        public int $calls = 0;

        public function embed(array $texts): array
        {
            $this->calls++;

            return ['vectors' => [array_fill(0, 1536, 0.1)], 'model' => 'fake', 'dims' => 1536];
        }
    };

    $handler = new StoreEmbeddingHandler($fake);

    $first = $handler->handle('cv_bullet', 7, 'Built APIs.', $admin->id);
    $second = $handler->handle('cv_bullet', 7, 'Built APIs.', $admin->id);

    expect($second->id)->toBe($first->id)->and($fake->calls)->toBe(1);
    expect(StudioEmbeddingEloquentModel::query()->count())->toBe(1);
});

it('refreshes vocabulary from the profile stack (T-027)', function (): void {
    $admin = coverageAdmin();
    $profile = coverageProfile($admin);

    $count = app(RefreshVocabularyHandler::class)->handle($profile->uuid, $admin->id);

    expect($count)->toBe(3)
        ->and(StudioVocabularyEloquentModel::query()->where('kind', 'strong')->count())->toBe(2)
        ->and(StudioVocabularyEloquentModel::query()->where('kind', 'never_seed')->count())->toBe(1);
});

it('expires postings unseen for the configured window (T-016)', function (): void {
    $admin = coverageAdmin();
    $profile = coverageProfile($admin);

    $posting = coveragePosting($admin, $profile, 'old');
    $posting->update([
        'first_seen_at' => now()->subDays(60),
        'last_seen_at' => now()->subDays(60),
    ]);

    $expired = app(ExpirePostingsHandler::class)->handle($admin->id);

    expect($expired)->toBe(1)
        ->and($posting->refresh()->is_expired)->toBeTrue()
        ->and($posting->alive_check_method)->toBe('harvest_absence');
});

it('shows own rates with uncertainty and gate flags (T-134)', function (): void {
    $admin = coverageAdmin();
    $profile = coverageProfile($admin);
    $posting = coveragePosting($admin, $profile, 'rates-1');
    $posting->update(['status' => 'applied', 'discovery_channel' => 'board']);

    StudioApplicationEloquentModel::query()->create([
        'user_id' => $admin->id,
        'posting_id' => $posting->id,
        'status' => 'applied',
        'outcome' => 'screening',
        'outcome_at' => now(),
    ]);

    $rows = app(GetOwnRatesHandler::class)->handle($admin->id);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->bucket)->toBe('board')
        ->and($rows[0]->rate)->toBe(1.0)
        ->and($rows[0]->gatePassed)->toBeFalse();
});

it('materialises apply_priority and reproduces fit order in neutral mode (T-131, SC-13)', function (): void {
    $admin = coverageAdmin();
    $profile = coverageProfile($admin);

    foreach (['priority-1', 'priority-2'] as $suffix) {
        $posting = coveragePosting($admin, $profile, $suffix);

        $posting->requirements()->create([
            'user_id' => $admin->id,
            'canonical_name' => 'Laravel',
            'tag' => 'required',
            'nature' => 'hard',
        ]);

        $this->actingAs($admin)->postJson("/cv-studio/postings/{$posting->uuid}/score", [
            'cv_skills' => [['name' => 'Laravel', 'evidence' => 'in_bullet', 'position_ratio' => 0.1]],
            'similarities' => ['title_cosine' => 0.8, 'responsibility_cosine' => 0.7],
            'signals' => ['experience' => 90, 'location' => 80, 'education' => 70, 'language' => 60],
            'cap_context' => ['readable' => true, 'credential_ok' => true, 'evidence_ok' => true],
        ])->assertCreated();
    }

    $refreshed = app(RefreshApplyPriorityHandler::class)->handle($admin->id, true);

    expect($refreshed)->toBe(2);

    $priorityOrder = StudioScoreEloquentModel::query()
        ->where('user_id', $admin->id)
        ->orderByDesc('apply_priority')
        ->pluck('total_score')
        ->map(static fn ($value): float => (float) $value)
        ->all();

    $fitOrder = StudioScoreEloquentModel::query()
        ->where('user_id', $admin->id)
        ->orderByDesc('total_score')
        ->pluck('total_score')
        ->map(static fn ($value): float => (float) $value)
        ->all();

    // Neutral mode: priority order IS fit order (opportunity never alters fit).
    expect($priorityOrder)->toBe($fitOrder);
});

it('recomputes a stored score offline to the identical total (SC-2, T-063)', function (): void {
    $admin = coverageAdmin();
    $profile = coverageProfile($admin);
    $posting = coveragePosting($admin, $profile, 'recompute-1');

    $posting->requirements()->create([
        'user_id' => $admin->id,
        'canonical_name' => 'Laravel',
        'tag' => 'required',
        'nature' => 'hard',
    ]);

    $score = $this->actingAs($admin)->postJson("/cv-studio/postings/{$posting->uuid}/score", [
        'cv_skills' => [['name' => 'Laravel', 'evidence' => 'in_bullet', 'position_ratio' => 0.1]],
        'similarities' => ['title_cosine' => 0.8, 'responsibility_cosine' => 0.7],
        'signals' => ['experience' => 90, 'location' => 80, 'education' => 70, 'language' => 60],
        'cap_context' => ['readable' => true, 'credential_ok' => true, 'evidence_ok' => true],
    ])->assertCreated()->json('data');

    $recomputed = app(RescorePostingHandler::class)->recomputeFromStored(
        $posting->uuid,
        $admin->id,
        [...config('cv-job-studio'), 'rules_version' => 2],
    );

    expect($recomputed['matches_stored'])->toBeTrue()
        ->and($recomputed['total'])->toBe((float) $score['total_score']);
});
