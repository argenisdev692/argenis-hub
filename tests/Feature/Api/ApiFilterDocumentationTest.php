<?php

declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Modules\Authorization\Application\DTOs\PermissionFilterData;
use Modules\Authorization\Application\DTOs\RoleFilterData;
use Modules\Availability\Application\DTOs\AvailabilityExceptionFilterData;
use Modules\Availability\Application\DTOs\AvailabilityRuleFilterData;
use Modules\Blog\Application\DTOs\BlogCategoryFilterData;
use Modules\Campaigns\Application\DTOs\CampaignFilterData;
use Modules\Cvs\Application\DTOs\CvFilterData;
use Modules\Invoices\Application\DTOs\InvoiceFilterData;
use Modules\Post\Application\DTOs\PostFilterData;
use Modules\SocialMedia\Application\DTOs\SocialMediaContentFilterData;

/**
 * Every filter a list endpoint accepts must appear in the OpenAPI document.
 *
 * The endpoints inject their filter `Data` object precisely so Scramble can
 * read its rules; nothing else makes the parameters appear. That makes the
 * failure mode silent and easy to reintroduce — swap the injected parameter
 * back for `SomeFilterData::validateAndCreate($request)`, or drop the import on
 * a `#[QueryParameter]`, and the spec quietly loses the filters while every
 * other check stays green. Both of those happened while this surface was being
 * built, and neither was caught by tests, Pint or PHPStan.
 *
 * So the assertion is made against a freshly generated document rather than the
 * committed `api.json`, which is a build artifact a stale copy of would happily
 * satisfy.
 */
beforeEach(function (): void {
    $this->document = app(Generator::class)();
});

dataset('documented list endpoints', [
    'roles' => ['/roles', RoleFilterData::class],
    'permissions' => ['/permissions', PermissionFilterData::class],
    'blog categories' => ['/blog-categories', BlogCategoryFilterData::class],
    'campaigns' => ['/campaigns', CampaignFilterData::class],
    'cvs' => ['/cvs', CvFilterData::class],
    'invoices' => ['/invoices', InvoiceFilterData::class],
    'posts' => ['/posts', PostFilterData::class],
    'social media' => ['/social-media', SocialMediaContentFilterData::class],
    'availability rules' => ['/availability-rules', AvailabilityRuleFilterData::class],
    'availability exceptions' => ['/availability-exceptions', AvailabilityExceptionFilterData::class],
]);

/**
 * @return list<string>
 */
function documentedQueryParameters(array $document, string $path): array
{
    $parameters = $document['paths'][$path]['get']['parameters'] ?? [];

    return array_values(array_map(
        static fn (array $parameter): string => $parameter['name'],
        array_filter(
            $parameters,
            static fn (array $parameter): bool => ($parameter['in'] ?? null) === 'query',
        ),
    ));
}

it('documents every filter its DTO accepts', function (string $path, string $dto): void {
    $documented = documentedQueryParameters($this->document, $path);
    $accepted = array_keys($dto::rules());

    expect(array_diff($accepted, $documented))->toBe(
        [],
        "{$path} accepts filters that the OpenAPI document does not describe.",
    );
})->with('documented list endpoints');

it('documents per_page on every paginated list endpoint', function (string $path): void {
    expect(documentedQueryParameters($this->document, $path))->toContain('per_page');
})->with('documented list endpoints');

/**
 * A parameter with a name and a type but no prose tells a client what to send
 * and nothing about when to send it. The descriptions come from the comments
 * above the rules in each filter DTO, so this is what makes adding a rule
 * without documenting it a failing build rather than a quiet gap in the spec.
 */
it('describes every query parameter it documents', function (): void {
    $undescribed = [];

    foreach ($this->document['paths'] as $path => $operations) {
        foreach ($operations['get']['parameters'] ?? [] as $parameter) {
            if (($parameter['in'] ?? null) !== 'query') {
                continue;
            }

            if (trim((string) ($parameter['description'] ?? '')) === '') {
                $undescribed[] = "{$path}?{$parameter['name']}";
            }
        }
    }

    expect($undescribed)->toBe(
        [],
        'Every documented query parameter needs a description a client can act on.',
    );
});

it('describes the two orthogonal invoice axes for API consumers', function (): void {
    $parameters = $this->document['paths']['/invoices']['get']['parameters'];
    $byName = array_column($parameters, null, 'name');

    // Descriptions are harvested from the comments above the rules in
    // `InvoiceFilterData`. A maintainer aside written there would be published
    // verbatim to the spec, so it is worth asserting these say something a
    // client can act on.
    expect($byName['status']['description'] ?? '')->toContain('suspended')
        ->and($byName['payment_status']['description'] ?? '')->toContain('independent of');

    // And the schemas stay inferred from the `in:` lists, not hand-copied.
    expect($byName['status']['schema']['enum'] ?? [])->toContain('all')
        ->and($byName['payment_status']['schema']['enum'] ?? [])->toContain('paid', 'unpaid');
});
