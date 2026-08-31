<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Tests\TestCase;

final class SocialMediaContentExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_exports_content_as_csv(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');
        SocialMediaContentEloquentModel::factory()->ready()->create();

        $response = $this->actingAs($admin)->get('/social-media/export?format=csv');

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_export_requires_export_permission(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        $this->actingAs($plain)->get('/social-media/export?format=csv')->assertForbidden();
    }
}
