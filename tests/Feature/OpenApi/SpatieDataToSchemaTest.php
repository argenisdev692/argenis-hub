<?php

declare(strict_types=1);

use App\Models\User;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Modules\Auth\Application\DTOs\ApiTokenData;
use Modules\Auth\Application\DTOs\AuthenticatedUserData;
use Modules\Auth\Application\DTOs\AuthSessionData;
use Shared\Infrastructure\OpenApi\SpatieDataToSchema;

/**
 * Scramble documents Spatie `Data` responses as EMPTY schemas without this
 * extension (its Laravel-Data support is a paid feature), so every API consumer
 * would see a response key with no fields under it.
 */
function dataSchema(string $dataClass): array
{
    $extension = new SpatieDataToSchema(
        Mockery::mock(Infer::class),
        Mockery::mock(TypeTransformer::class),
        new Components,
    );

    return $extension->toSchema(new ObjectType($dataClass))->toArray();
}

function handlesType(string $class): bool
{
    return (new SpatieDataToSchema(
        Mockery::mock(Infer::class),
        Mockery::mock(TypeTransformer::class),
        new Components,
    ))->shouldHandle(new ObjectType($class));
}

test('it claims Spatie Data classes and leaves everything else alone', function (): void {
    expect(handlesType(AuthenticatedUserData::class))->toBeTrue()
        ->and(handlesType(ApiTokenData::class))->toBeTrue()
        ->and(handlesType(User::class))->toBeFalse()
        ->and(handlesType(DateTimeImmutable::class))->toBeFalse();
});

test('the token schema documents every field', function (): void {
    $schema = dataSchema(ApiTokenData::class);

    expect($schema['type'])->toBe('object')
        ->and(array_keys($schema['properties']))
        ->toBe(['access_token', 'token_type', 'expires_at', 'abilities'])
        ->and($schema['required'])
        ->toBe(['access_token', 'token_type', 'expires_at', 'abilities']);
});

test('MapOutputName is honoured so documented keys match the JSON keys', function (): void {
    $schema = dataSchema(AuthenticatedUserData::class);

    // The PHP property is `emailVerifiedAt`; the wire format is snake_case.
    expect($schema['properties'])->toHaveKey('email_verified_at')
        ->and($schema['properties'])->toHaveKey('two_factor_enabled')
        ->and($schema['properties'])->not->toHaveKey('emailVerifiedAt');
});

test('nullable properties are typed as nullable and left out of required', function (): void {
    $schema = dataSchema(AuthenticatedUserData::class);

    expect($schema['properties']['email_verified_at']['type'])->toBe(['string', 'null'])
        ->and($schema['required'])->not->toContain('email_verified_at')
        ->and($schema['required'])->toContain('uuid');
});

test('scalar types are mapped rather than defaulted to string', function (): void {
    $schema = dataSchema(AuthenticatedUserData::class);

    expect($schema['properties']['two_factor_enabled']['type'])->toBe('boolean')
        ->and($schema['properties']['uuid']['type'])->toBe('string');
});

test('list<string> is documented as an array of strings', function (): void {
    $schema = dataSchema(AuthenticatedUserData::class);

    expect($schema['properties']['roles']['type'])->toBe('array')
        ->and($schema['properties']['roles']['items']['type'])->toBe('string');
});

test('it also covers DTOs outside the API module', function (): void {
    $schema = dataSchema(AuthSessionData::class);

    expect($schema['properties'])->toHaveKey('ip_address')
        ->and($schema['properties'])->toHaveKey('is_current')
        ->and($schema['properties']['is_current']['type'])->toBe('boolean');
});
