<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\SocialMedia\Domain\Enums\SocialMediaImageMode;
use Modules\SocialMedia\Infrastructure\Ai\SocialMediaImagePromptFactory;
use Modules\SocialMedia\Infrastructure\Queue\GenerateSocialMediaContentJob;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\Branding\BrandPalette;
use Tests\TestCase;

/**
 * `image_mode` is the three-way cost switch on Step 2: `full` renders the
 * cover plus all five platform graphics, `base` renders palette background
 * plates only, `none` bills no image call at all. Mirrors Post's
 * PostCategoryIdeationAndImageModeTest.
 */
final class SocialMediaImageModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        return $admin;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $imageMode): array
    {
        return [
            'topic' => 'Laravel 13 release',
            'provider' => 'openai',
            'language' => 'en',
            'business_goal' => 'awareness',
            'brand_voice' => 'professional',
            'funnel_stage' => 'tofu',
            'image_mode' => $imageMode,
            'generate_voiceover' => false,
        ];
    }

    /**
     * @return list<array{0: string}>
     */
    public static function imageModeProvider(): array
    {
        return [['full'], ['base'], ['none']];
    }

    #[DataProvider('imageModeProvider')]
    public function test_every_image_mode_is_accepted_and_recorded(string $imageMode): void
    {
        Queue::fake();

        $this->actingAs($this->superAdmin())
            ->postJson('/social-media/ai/generate-content', $this->payload($imageMode))
            ->assertStatus(202);

        Queue::assertPushed(
            GenerateSocialMediaContentJob::class,
            static fn (GenerateSocialMediaContentJob $job): bool => true,
        );

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'social_media',
            'event' => 'social_media.ai.generation_started',
        ]);
    }

    public function test_an_unknown_image_mode_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson('/social-media/ai/generate-content', $this->payload('watercolour'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('image_mode');
    }

    public function test_image_mode_defaults_to_full_when_omitted(): void
    {
        Queue::fake();

        $payload = $this->payload('full');
        unset($payload['image_mode']);

        $this->actingAs($this->superAdmin())
            ->postJson('/social-media/ai/generate-content', $payload)
            ->assertStatus(202);

        Queue::assertPushed(GenerateSocialMediaContentJob::class);
    }

    public function test_base_mode_renders_the_palette_plate_regardless_of_the_agent_route(): void
    {
        $prompts = new SocialMediaImagePromptFactory;

        $base = $prompts->forMode(SocialMediaImageMode::Base, 'c', 'Title', 'a node network');

        $this->assertSame($prompts->background(), $base);
        $this->assertStringContainsString(BrandPalette::BACKGROUND, $base);
        $this->assertStringNotContainsString('Title', $base);
    }

    public function test_full_mode_honours_the_agent_route(): void
    {
        $prompts = new SocialMediaImagePromptFactory;

        $composite = $prompts->forMode(SocialMediaImageMode::Full, 'a', 'Ship faster', 'a node network');
        $abstract = $prompts->forMode(SocialMediaImageMode::Full, 'b', 'Ship faster', 'a node network');

        $this->assertStringContainsString('Ship faster', $composite);
        $this->assertStringNotContainsString('Ship faster', $abstract);
        $this->assertStringContainsString(BrandPalette::PRIMARY_ACCENT, $abstract);
    }

    public function test_only_full_mode_honours_the_svg_roadmap_route(): void
    {
        $this->assertTrue(SocialMediaImageMode::Full->honorsImageRoute());
        $this->assertFalse(SocialMediaImageMode::Base->honorsImageRoute());
        $this->assertFalse(SocialMediaImageMode::None->honorsImageRoute());

        $this->assertTrue(SocialMediaImageMode::Full->rendersImage());
        $this->assertTrue(SocialMediaImageMode::Base->rendersImage());
        $this->assertFalse(SocialMediaImageMode::None->rendersImage());
    }
}
