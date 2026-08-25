<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'Str0ng-First-Passw0rd!',
        'password_confirmation' => 'Str0ng-First-Passw0rd!',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

// Spec 001 FR-01 — the 12+ character policy with mixed case, a number and a
// symbol applies in every environment, not only in production.
test('a password that does not meet the policy is rejected', function () {
    $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'weak@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});

// Spec 001 FR-04 — registration is capped at 3 per IP per hour.
test('registration is rate limited per IP', function () {
    foreach (range(1, 3) as $index) {
        $this->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => "test{$index}@example.com",
            'password' => 'Str0ng-First-Passw0rd!',
            'password_confirmation' => 'Str0ng-First-Passw0rd!',
        ]);

        $this->post(route('logout'));
    }

    $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test4@example.com',
        'password' => 'Str0ng-First-Passw0rd!',
        'password_confirmation' => 'Str0ng-First-Passw0rd!',
    ])->assertTooManyRequests();
});
