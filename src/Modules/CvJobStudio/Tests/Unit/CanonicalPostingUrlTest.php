<?php

declare(strict_types=1);

use Modules\CvJobStudio\Domain\ValueObjects\CanonicalPostingUrl;

it('drops the fragment, tracking params and trailing slash', function (): void {
    $url = new CanonicalPostingUrl('https://boards.greenhouse.io/acme/jobs/123/?utm_source=linkedin&gclid=abc#main');

    expect($url->value)->toBe('https://boards.greenhouse.io/acme/jobs/123')
        ->and($url->hash())->toBe(hash('sha256', 'https://boards.greenhouse.io/acme/jobs/123'));
});

it('collides across tracking variants of the same posting', function (): void {
    $a = new CanonicalPostingUrl('https://example.com/jobs/9?utm_medium=email');
    $b = new CanonicalPostingUrl('https://example.com/jobs/9/?fbclid=x&utm_campaign=y');

    expect($a->hash())->toBe($b->hash());
});

it('keeps non-tracking query params', function (): void {
    $url = new CanonicalPostingUrl('https://boards.greenhouse.io/acme/jobs?content=true&utm_source=x');

    expect($url->value)->toBe('https://boards.greenhouse.io/acme/jobs?content=true');
});
