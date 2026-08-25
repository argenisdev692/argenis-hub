<?php

/**
 * Architecture tests — enforce the layer-import rules defined in
 * `.opencode/skills/ARCHITECTURE-PHP/SKILL.md` (Layer Imports table)
 * and the cross-module boundary rules. These tests fail the build when
 * a new import violates the architecture.
 *
 * Rules for layers that no module implements yet are skipped
 * automatically; they activate the moment the first module creates
 * that layer.
 *
 * Run: php artisan test --compact tests/Architecture
 */
$moduleDirs = glob(__DIR__.'/../../src/Modules/*', GLOB_ONLYDIR) ?: [];

$layerExists = static function (string $layer) use ($moduleDirs): bool {
    foreach ($moduleDirs as $dir) {
        if (is_dir($dir.'/'.$layer)) {
            return true;
        }
    }

    return false;
};

$hasDomain = $layerExists('Domain');
$hasApplication = $layerExists('Application');

// ─────────────────────────────────────────────────────────────
// Domain layer — pure. No Eloquent, no facades, no HTTP, no queues.
// Allowed: own VOs/Entities/Events, Shared\Domain\*, native PHP.
// ─────────────────────────────────────────────────────────────
if ($hasDomain) {
    arch('domain stays pure — no Eloquent')
        ->expect('Modules\\*\\Domain')
        ->not->toUse('Illuminate\\Database\\Eloquent');

    arch('domain stays pure — no facades')
        ->expect('Modules\\*\\Domain')
        ->not->toUse('Illuminate\\Support\\Facades');

    arch('domain stays pure — no HTTP')
        ->expect('Modules\\*\\Domain')
        ->not->toUse('Illuminate\\Http');

    arch('domain stays pure — no queues')
        ->expect('Modules\\*\\Domain')
        ->not->toUse('Illuminate\\Contracts\\Queue');

    arch('domain stays pure — no controllers')
        ->expect('Modules\\*\\Domain')
        ->not->toUse('Illuminate\\Routing');

    arch('domain may only use Shared\\Domain')
        ->expect('Modules\\*\\Domain')
        ->not->toUse('Shared\\Application')
        ->not->toUse('Shared\\Infrastructure');
}

// ─────────────────────────────────────────────────────────────
// Application layer — no Eloquent, no facades, no HTTP.
// Illuminate\Contracts\* (interfaces only) IS allowed by design.
// ─────────────────────────────────────────────────────────────
if ($hasApplication) {
    arch('application does not use Eloquent')
        ->expect('Modules\\*\\Application')
        ->not->toUse('Illuminate\\Database\\Eloquent');

    arch('application does not use facades')
        ->expect('Modules\\*\\Application')
        ->not->toUse('Illuminate\\Support\\Facades');

    arch('application does not use HTTP')
        ->expect('Modules\\*\\Application')
        ->not->toUse('Illuminate\\Http');
}

// ─────────────────────────────────────────────────────────────
// Cross-module boundaries — a module's Infrastructure is private.
// Public API of a module = Domain\Events, Application\DTOs, Domain\Ports.
// Seeders and factories are composition roots and are exempt, and so is
// App\Models: the backend skill (§4 "user_id — MANDATORY bidirectional wiring")
// requires App\Models\User to declare the inverse hasMany for every module table
// carrying user_id, which cannot be done without naming the Eloquent model.
// NOTE: Pest cannot express "same module only" — this rule still
// catches App\ and Shared\ reaching into module internals; the
// same-module half of the rule lives in the skill and code review.
// ─────────────────────────────────────────────────────────────
arch('module infrastructure is not reachable from app-layer code')
    ->expect('Modules\\*\\Infrastructure')
    ->toOnlyBeUsedIn('Modules')
    ->ignoring(['App\\Models', 'Database\\Factories', 'Database\\Seeders', 'Tests']);

// ─────────────────────────────────────────────────────────────
// Shared kernel — one-way dependency. Shared must not import Modules.
// ─────────────────────────────────────────────────────────────
arch('shared kernel does not depend on modules')
    ->expect('Shared')
    ->not->toUse('Modules\\');

// ─────────────────────────────────────────────────────────────
// Hygiene — no debug leftovers anywhere.
// ─────────────────────────────────────────────────────────────
arch('no debug statements')
    ->expect(['dd', 'dump', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('no eval')
    ->expect('eval')
    ->not->toBeUsed();
