<?php

declare(strict_types=1);

use App\Models\User;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * `POST /api/public/contact-supports` — the landing-page contact form. No
 * authentication, so the allowlist on the way out, the validation gate, and the
 * per-IP throttle are the load-bearing assertions (same reasoning as
 * `PublicServiceApiTest`).
 */

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function publicContactSupportPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+14155552671',
        'subject' => 'Landing page enquiry',
        'message' => 'Please send me a quote for a one-page marketing site.',
        'sms_consent' => true,
        ...$overrides,
    ];
}

it('is reachable without authentication and records the request', function (): void {
    $this->postJson(route('api.public.contact-supports'), publicContactSupportPayload())
        ->assertCreated()
        ->assertJsonPath('subject', 'Landing page enquiry')
        ->assertJsonStructure(['uuid', 'subject']);

    $support = ContactSupportEloquentModel::query()->where('email', 'ada@example.com')->firstOrFail();

    expect($support->readed)->toBeFalse()
        ->and($support->is_spam)->toBeFalse()
        ->and($support->spam_score)->toBe(0)
        ->and($support->user_id)->toBeNull()
        ->and($support->sms_consent)->toBeTrue();
});

it('attributes the request to a signed-in visitor', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.public.contact-supports'), publicContactSupportPayload(['email' => 'member@example.com']))
        ->assertCreated();

    $support = ContactSupportEloquentModel::query()->where('email', 'member@example.com')->firstOrFail();
    expect($support->user_id)->toBe($user->id);
});

it('never leaks internal columns or triage state in the acknowledgement', function (): void {
    $body = $this->postJson(route('api.public.contact-supports'), publicContactSupportPayload())
        ->assertCreated()
        ->getContent() ?: '';

    expect($body)
        ->not->toContain('"id"')
        ->not->toContain('"user_id"')
        ->not->toContain('"is_spam"')
        ->not->toContain('"spam_score"')
        ->not->toContain('"readed"');
});

it('rejects invalid input', function (array $overrides, string $field): void {
    $this->postJson(route('api.public.contact-supports'), publicContactSupportPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing first name' => [['first_name' => ''], 'first_name'],
    'missing last name' => [['last_name' => ''], 'last_name'],
    'malformed email' => [['email' => 'nope'], 'email'],
    'malformed phone' => [['phone' => 'ring-me'], 'phone'],
    'subject too long' => [['subject' => str_repeat('a', 151)], 'subject'],
    'message too short' => [['message' => 'hi'], 'message'],
]);

it('throttles a flood from one IP', function (): void {
    foreach (range(1, 5) as $ignored) {
        $this->postJson(route('api.public.contact-supports'), publicContactSupportPayload())
            ->assertCreated();
    }

    $this->postJson(route('api.public.contact-supports'), publicContactSupportPayload())
        ->assertStatus(429);
});
