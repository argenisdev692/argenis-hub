<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostAiGenerationEloquentModel;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;

/**
 * The inverse side of the four content-module foreign keys that point at
 * `users`.
 *
 * The `belongsTo` halves shipped with their modules; the `hasMany` halves did
 * not, which left `posts.user_id`, `post_ai_generations.created_by`,
 * `social_media_contents.created_by` and `campaigns.created_by` one-directional
 * against the project rule that every FK carries both sides. This test is what
 * keeps them from drifting back.
 *
 * Three of the four hang off `created_by`, not the conventional `user_id`, so
 * the explicit FK argument is the thing most worth pinning — a silent revert to
 * the convention would resolve `user_id` on a table that has no such column.
 */
it('resolves the posts a user authored', function (): void {
    $author = User::factory()->create();
    $post = PostEloquentModel::factory()->forUser($author)->create();
    PostEloquentModel::factory()->create();

    expect($author->posts)->toHaveCount(1)
        ->and($author->posts->first()?->getKey())->toBe($post->getKey())
        ->and($post->user?->getKey())->toBe($author->getKey());
});

it('resolves the AI post generations a user launched', function (): void {
    $causer = User::factory()->create();
    $generation = PostAiGenerationEloquentModel::factory()->forUser($causer)->create();
    PostAiGenerationEloquentModel::factory()->create();

    expect($causer->postAiGenerations)->toHaveCount(1)
        ->and($causer->postAiGenerations->first()?->getKey())->toBe($generation->getKey())
        ->and($generation->causer?->getKey())->toBe($causer->getKey());
});

it('resolves the social media contents a user created', function (): void {
    $creator = User::factory()->create();
    $content = SocialMediaContentEloquentModel::factory()->forUser($creator)->create();
    SocialMediaContentEloquentModel::factory()->create();

    expect($creator->socialMediaContents)->toHaveCount(1)
        ->and($creator->socialMediaContents->first()?->getKey())->toBe($content->getKey())
        ->and($content->user?->getKey())->toBe($creator->getKey());
});

it('resolves the campaigns a user created', function (): void {
    $creator = User::factory()->create();
    $campaign = CampaignEloquentModel::factory()->forUser($creator)->create();
    CampaignEloquentModel::factory()->create();

    expect($creator->campaigns)->toHaveCount(1)
        ->and($creator->campaigns->first()?->getKey())->toBe($campaign->getKey())
        ->and($campaign->creator?->getKey())->toBe($creator->getKey());
});

it('eager-loads every new relation without a lazy-loading violation', function (): void {
    $owner = User::factory()->create();

    PostEloquentModel::factory()->forUser($owner)->create();
    PostAiGenerationEloquentModel::factory()->forUser($owner)->create();
    SocialMediaContentEloquentModel::factory()->forUser($owner)->create();
    CampaignEloquentModel::factory()->forUser($owner)->create();

    // Model::shouldBeStrict() is on outside production, so an un-eager-loaded
    // access below would throw rather than quietly firing an extra query. The
    // column lists must name the real FK — `created_by` on three of the four.
    $loaded = User::query()
        ->with([
            'posts:id,user_id,post_title',
            'postAiGenerations:id,created_by,topic',
            'socialMediaContents:id,created_by,topic',
            'campaigns:id,created_by,topic',
        ])
        ->findOrFail($owner->getKey());

    expect($loaded->posts)->toHaveCount(1)
        ->and($loaded->postAiGenerations)->toHaveCount(1)
        ->and($loaded->socialMediaContents)->toHaveCount(1)
        ->and($loaded->campaigns)->toHaveCount(1);
});

it('keeps the content when its author is deleted', function (): void {
    $author = User::factory()->create();
    $post = PostEloquentModel::factory()->forUser($author)->create();
    $campaign = CampaignEloquentModel::factory()->forUser($author)->create();

    // Every one of these FKs is ON DELETE SET NULL: authorship is an attribution,
    // not an ownership that should cascade the content away.
    $author->forceDelete();

    expect($post->fresh()?->user_id)->toBeNull()
        ->and($campaign->fresh()?->created_by)->toBeNull();
});
