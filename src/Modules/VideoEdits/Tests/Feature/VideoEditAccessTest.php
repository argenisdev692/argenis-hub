<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

dataset('video edit endpoints', function (): array {
    $uuid = '0191e3a4-7c2b-7d3e-9f10-1234567890ab';

    return [
        'list (VIEW_ANY)' => ['get', '/data/admin/video-edits'],
        'create (CREATE)' => ['post', '/data/admin/video-edits'],
        'show (VIEW)' => ['get', "/data/admin/video-edits/{$uuid}"],
        'download link (DOWNLOAD)' => ['get', "/data/admin/video-edits/{$uuid}/download-url"],
        'submit (CREATE)' => ['post', "/data/admin/video-edits/{$uuid}/submit"],
        'retry (RETRY)' => ['post', "/data/admin/video-edits/{$uuid}/retry"],
        'delete (DELETE)' => ['delete', "/data/admin/video-edits/{$uuid}"],
    ];
});

it('requires a signed-in user on every endpoint', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertUnauthorized();
})->with('video edit endpoints');

it('requires the matching VIDEO_EDITS permission on every endpoint', function (string $method, string $uri): void {
    $this->actingAs(VideoEditTestUsers::withoutVideoEditPermissions())
        ->json($method, $uri)
        ->assertForbidden();
})->with('video edit endpoints');

it('grants the video edit tools to admins', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('ADMIN');

    $this->actingAs($admin)->getJson('/data/admin/video-edits')->assertOk();
});

it('rate-limits every endpoint that changes data (OWASP §14)', function (): void {
    $mutating = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'data/admin/video-edits')
            && array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []);

    expect($mutating)->toHaveCount(4);

    $mutating->each(function ($route): void {
        expect(collect($route->gatherMiddleware())->contains(fn ($middleware): bool => is_string($middleware) && str_starts_with($middleware, 'throttle:')))
            ->toBeTrue("{$route->uri()} is not throttled");
    });
});

it('seeds the video edit permissions and not the obsolete export ones', function (): void {
    $names = DB::table('permissions')->where('name', 'like', '%VIDEO%')->orderBy('name')->pluck('name')->all();

    expect($names)->toBe([
        'CREATE_VIDEO_EDITS',
        'DELETE_VIDEO_EDITS',
        'DOWNLOAD_VIDEO_EDITS',
        'RETRY_VIDEO_EDITS',
        'VIEW_ANY_VIDEO_EDITS',
        'VIEW_VIDEO_EDITS',
    ]);
});
