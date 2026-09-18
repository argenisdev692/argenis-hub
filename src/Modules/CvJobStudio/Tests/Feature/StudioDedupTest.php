<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CvJobStudio\Application\Commands\DeduplicatePostingsHandler;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingSourceEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSourceEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * One job, many sources, no false merges (T-096, CHG-2, T-015): the same
 * Greenhouse posting mirrored on Arbeitnow, WWR and Tavily collapses into
 * one row with four source rows; a same-title posting from another week
 * stays separate.
 */
it('merges four URLs of one job and keeps other weeks apart', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    $profile = StudioProfileEloquentModel::query()->create([
        'user_id' => $admin->id,
        'name' => 'Fullstack',
        'slug' => 'fullstack',
        'flow' => 'fullstack',
        'is_active' => true,
        'rules' => config('cv-job-studio'),
        'rules_version' => 2,
    ]);

    $sourceNames = ['greenhouse-dedup', 'arbeitnow-dedup', 'wwr-dedup', 'tavily-dedup'];
    $sourceIds = [];

    foreach ($sourceNames as $name) {
        $sourceIds[] = StudioSourceEloquentModel::query()->create([
            'user_id' => $admin->id, 'kind' => 'board_api', 'name' => $name,
            'status' => 'active', 'access_mode' => 'api_feed',
        ])->id;
    }

    $urls = [
        'https://boards.greenhouse.io/acme/jobs/4242',
        'https://arbeitnow.com/jobs/acme-senior-laravel-4242',
        'https://weworkremotely.com/remote-jobs/acme-senior-laravel',
        'https://www.example-aggregator.com/jobs/4242?utm_source=tavily',
    ];

    foreach ($urls as $index => $url) {
        $posting = StudioPostingEloquentModel::query()->create([
            'user_id' => $admin->id,
            'profile_id' => $profile->id,
            'canonical_url' => $url,
            'url_hash' => hash('sha256', $url),
            'employer_name' => 'Acme',
            'title' => 'Senior Laravel Developer',
            'location_text' => 'Remote, EU',
            'posted_at' => '2026-09-14',
            'status' => 'new',
        ]);

        StudioPostingSourceEloquentModel::query()->create([
            'user_id' => $admin->id,
            'posting_id' => $posting->id,
            'source_id' => $sourceIds[$index],
            'source_url' => $url,
        ]);
    }

    $otherWeek = StudioPostingEloquentModel::query()->create([
        'user_id' => $admin->id,
        'profile_id' => $profile->id,
        'canonical_url' => 'https://boards.greenhouse.io/acme/jobs/9999',
        'url_hash' => hash('sha256', 'https://boards.greenhouse.io/acme/jobs/9999'),
        'employer_name' => 'Acme',
        'title' => 'Senior Laravel Developer',
        'location_text' => 'Remote, EU',
        'posted_at' => '2026-09-21',
        'status' => 'new',
    ]);

    $merged = app(DeduplicatePostingsHandler::class)->handle($admin->id);

    expect($merged)->toBe(3)
        ->and(StudioPostingEloquentModel::query()->count())->toBe(2)
        ->and(
            StudioPostingSourceEloquentModel::query()
                ->where('posting_id', StudioPostingEloquentModel::query()->where('canonical_url', $urls[0])->value('id'))
                ->count()
        )->toBe(4)
        ->and($otherWeek->refresh()->trashed())->toBeFalse();
});
