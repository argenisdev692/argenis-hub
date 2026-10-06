<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Route;
use Modules\VideoEdits\Application\Queries\ListInstructionProfilesHandler;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;

/**
 * The instruction profiles the AI edit form offers as a starting point.
 *
 * These matter more than a settings screen usually would: `ai_edit.instructions`
 * is free text typed per video, so the same standing notes either get retyped
 * every time or quietly stop being applied at all.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    Route::middleware(['web', 'auth', 'verified'])->prefix('data/admin/video-edits')
        ->name('video-edits.admin.')
        ->group(__DIR__.'/../../Infrastructure/Routes/web.php');
});

it('offers the configured profiles to an editor', function (): void {
    $response = $this->actingAs(VideoEditTestUsers::editor())
        ->getJson(route('video-edits.admin.instruction-profiles'))
        ->assertOk()
        ->assertJsonPath('data.0.key', 'supervisor_lms')
        ->assertJsonPath('data.0.name', 'Supervisor LMS');

    expect($response->json('data.0.instructions'))
        ->toContain('Nunca cortes mientras una herramienta esta trabajando')
        ->toContain('Enseña el paso completo');
});

it('ships a profile that carries every standing note from the review', function (): void {
    $instructions = config('video-edit.ai.instruction_profiles.supervisor_lms.instructions');

    // Each bullet OBSERVACIONES.txt asks for on every future recording.
    expect($instructions)
        ->toContain('Ritmo dinamico')
        ->toContain('Cortes limpios')
        ->toContain('No leer los prompts en voz alta')
        ->toContain('Ejercicios practicos')
        ->toContain('Enseña el paso completo')
        ->toContain('Nunca cortes mientras una herramienta esta trabajando');
});

it('never sends a profile longer than the field accepts', function (): void {
    config()->set('video-edit.ai.max_instructions_characters', 40);
    config()->set('video-edit.ai.instruction_profiles.supervisor_lms.instructions', str_repeat('a', 500));

    $profiles = app(ListInstructionProfilesHandler::class)->handle();

    expect($profiles[0]->instructions)->toHaveLength(40);
});

it('skips a profile that has no usable text', function (): void {
    config()->set('video-edit.ai.instruction_profiles', [
        'empty_name' => ['name' => '  ', 'instructions' => 'algo'],
        'no_text' => ['name' => 'Sin texto', 'instructions' => ''],
        'not_an_array' => 'nope',
        'good' => ['name' => 'Válido', 'instructions' => 'contenido'],
    ]);

    $profiles = app(ListInstructionProfilesHandler::class)->handle();

    expect($profiles)->toHaveCount(1)
        ->and($profiles[0]->key)->toBe('good');
});

it('refuses the profiles to a user without the video edit permission', function (): void {
    $this->actingAs(VideoEditTestUsers::withoutVideoEditPermissions())
        ->getJson(route('video-edits.admin.instruction-profiles'))
        ->assertForbidden();
});
