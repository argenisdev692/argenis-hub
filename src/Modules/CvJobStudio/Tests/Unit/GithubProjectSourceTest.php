<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Infrastructure\Cvs\EloquentPortfolioProjectSource;
use Modules\CvJobStudio\Infrastructure\Projects\CompositeProjectSource;
use Modules\CvJobStudio\Infrastructure\Projects\GithubProjectSource;

function githubRepos(array $repos): void
{
    Http::fake(['api.github.com/*' => Http::response($repos, 200)]);
}

it('returns no projects without a token and never calls github', function (): void {
    config()->set('services.github.token', '');
    Http::fake();

    expect((new GithubProjectSource)->projectsForUser(1))->toBe([]);

    Http::assertNothingSent();
});

it('maps own repos and skips forks and nameless rows', function (): void {
    config()->set('services.github.token', 'test-token');
    githubRepos([
        ['name' => 'vidula', 'description' => 'Ops MVP', 'html_url' => 'https://github.com/acme/vidula', 'fork' => false],
        ['name' => 'forked-theme', 'description' => null, 'html_url' => 'https://github.com/acme/forked-theme', 'fork' => true],
        ['name' => '', 'description' => null, 'html_url' => 'https://github.com/acme/unnamed', 'fork' => false],
    ]);

    $projects = (new GithubProjectSource)->projectsForUser(1);

    expect($projects)->toHaveCount(1)
        ->and($projects[0])->toMatchArray([
            'title' => 'vidula',
            'description' => 'Ops MVP',
            'url' => 'https://github.com/acme/vidula',
        ]);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-token'));
});

it('fails soft on github errors', function (): void {
    config()->set('services.github.token', 'test-token');
    Http::fake(['api.github.com/*' => Http::response('boom', 500)]);

    expect((new GithubProjectSource)->projectsForUser(1))->toBe([]);
});

it('merges portfolio projects before github repos without duplicates', function (): void {
    config()->set('services.github.token', 'test-token');
    githubRepos([
        ['name' => 'vidula', 'description' => null, 'html_url' => 'https://github.com/acme/vidula', 'fork' => false],
    ]);

    $composite = new CompositeProjectSource(new EloquentPortfolioProjectSource, new GithubProjectSource);

    $projects = $composite->projectsForUser(1);

    expect($projects)->toHaveCount(1)->and($projects[0]['title'])->toBe('vidula');
});
