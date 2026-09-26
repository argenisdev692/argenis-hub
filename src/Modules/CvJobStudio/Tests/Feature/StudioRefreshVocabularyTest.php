<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioVocabularyEloquentModel;

/*
| FR-32 / T-028. The command used to print a line and return SUCCESS without
| evaluating a trigger or calling the handler, so `studio_vocabulary` was never
| populated and discovery had no search terms at all. These tests pin the wired
| behaviour: a cache-miss seeds, a fresh vocabulary does not, `--forced` beats
| a fresh one, and the rejected stack lands as `never_seed` rather than as
| searchable terms.
*/

uses(RefreshDatabase::class);

function vocabularyProfile(array $overrides = []): StudioProfileEloquentModel
{
    return StudioProfileEloquentModel::query()->create([
        'user_id' => $overrides['user_id'] ?? User::factory()->create()->id,
        'name' => 'Fullstack',
        'slug' => 'fullstack-'.Str::lower(Str::random(6)),
        'flow' => 'fullstack',
        'is_active' => true,
        'stack_must' => $overrides['stack_must'] ?? ['PHP', 'Laravel', 'React'],
        'stack_reject' => $overrides['stack_reject'] ?? ['WordPress', '.NET'],
        'rules' => config('cv-job-studio'),
        'rules_version' => 2,
    ]);
}

it('seeds the vocabulary on a cache miss so discovery has search terms (FR-32)', function (): void {
    $profile = vocabularyProfile();

    expect(StudioVocabularyEloquentModel::query()->count())->toBe(0);

    $this->artisan('studio:refresh-vocabulary', ['--profile' => $profile->uuid])
        ->assertSuccessful();

    expect(StudioVocabularyEloquentModel::query()->where('profile_id', $profile->id)->count())->toBe(5)
        ->and(StudioVocabularyEloquentModel::query()->where('term', 'React')->value('kind'))->toBe('strong')
        // The rejected stack must never be seeded as a searchable term.
        ->and(StudioVocabularyEloquentModel::query()->where('term', 'WordPress')->value('kind'))->toBe('never_seed')
        ->and(StudioVocabularyEloquentModel::query()->where('term', '.NET')->value('kind'))->toBe('never_seed');
});

it('leaves a fresh vocabulary alone and --forced refreshes it anyway (T-028)', function (): void {
    $profile = vocabularyProfile();

    $this->artisan('studio:refresh-vocabulary', ['--profile' => $profile->uuid])->assertSuccessful();

    StudioVocabularyEloquentModel::query()->where('term', 'React')->update(['refreshed_at' => now()]);

    // Not forced: the stale-week trigger is the only one that can fire here,
    // and a just-refreshed vocabulary is none.
    $this->artisan('studio:refresh-vocabulary', ['--profile' => $profile->uuid])
        ->expectsOutputToContain('nothing to do')
        ->assertSuccessful();

    $this->artisan('studio:refresh-vocabulary', ['--profile' => $profile->uuid, '--forced' => true])
        ->expectsOutputToContain('forced')
        ->assertSuccessful();
});

it('is idempotent: re-seeding updates in place instead of duplicating (FR-32)', function (): void {
    $profile = vocabularyProfile();

    $this->artisan('studio:refresh-vocabulary', ['--profile' => $profile->uuid, '--forced' => true])->assertSuccessful();
    $this->artisan('studio:refresh-vocabulary', ['--profile' => $profile->uuid, '--forced' => true])->assertSuccessful();

    expect(StudioVocabularyEloquentModel::query()->where('profile_id', $profile->id)->count())->toBe(5)
        ->and(StudioVocabularyEloquentModel::query()->where('term', 'React')->count())->toBe(1);
});

it('fails loudly on an unknown profile uuid instead of silently doing nothing', function (): void {
    $this->artisan('studio:refresh-vocabulary', ['--profile' => (string) Str::uuid7()])
        ->assertFailed();
});
