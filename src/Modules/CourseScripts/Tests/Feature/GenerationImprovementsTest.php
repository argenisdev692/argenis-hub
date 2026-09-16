<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Conversational;
use Modules\CourseScripts\Infrastructure\Ai\GeneratePracticeArtifactAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptClosingAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptOutlineAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptSectionAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerationRequestPolicy;
use Modules\CourseScripts\Infrastructure\Ai\LaravelAiScriptWriterAdapter;
use Modules\CourseScripts\Infrastructure\Ai\ProposeCourseBibleAgent;
use Modules\CourseScripts\Infrastructure\Ai\ReviewPracticeAgent;
use Modules\CourseScripts\Infrastructure\Ai\ReviewScriptAgent;
use Modules\CourseScripts\Infrastructure\Ai\WritingContextRenderer;
use Modules\CourseScripts\Tests\Support\CanonicalScriptFixture;
use Modules\CourseScripts\Tests\Support\CourseScriptTestUsers;
use Modules\CourseScripts\Tests\Support\RecordingAiClient;
use Modules\CourseScripts\Tests\Support\WritingContextFixture;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\AI\PromptCache\PromptCacheScope;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

describe('generation request policy', function (): void {
    it('pins models and timeouts per step from config', function (): void {
        config()->set('course-scripts.generation.models.section', 'gpt-5.4-2026-01-01');
        config()->set('course-scripts.generation.timeouts.section', 180);

        $policy = app(GenerationRequestPolicy::class);

        expect($policy->modelFor('section'))->toBe('gpt-5.4-2026-01-01')
            ->and($policy->timeoutFor('section'))->toBe(180)
            ->and($policy->modelFor('outline'))->toBeNull();
    });

    it('orders fallbacks without the primary and caps them at two', function (): void {
        config()->set('course-scripts.generation.failover_order', 'openai,anthropic,gemini,groq');

        $fallbacks = app(GenerationRequestPolicy::class)->fallbacksFor('openai');

        expect($fallbacks)->toBe(['anthropic', 'gemini']);
    });

    it('passes the pinned model and timeout to the provider', function (): void {
        RecordingAiClient::install([
            GenerateScriptOutlineAgent::class => CanonicalScriptFixture::outlinePayload(),
        ]);
        config()->set('course-scripts.generation.models.outline', 'gpt-5.4-2026-01-01');
        config()->set('course-scripts.generation.timeouts.outline', 120);

        app(LaravelAiScriptWriterAdapter::class)->outline(WritingContextFixture::video22(), 'openai');

        $recording = app(AIClientInterface::class);

        expect($recording->calls[0]['model'])->toBe('gpt-5.4-2026-01-01')
            ->and($recording->calls[0]['timeout'])->toBe(120);
    });
});

describe('conversational memory', function (): void {
    it('every generation agent is conversational and starts with no history', function (): void {
        $agents = [
            GenerateScriptOutlineAgent::class,
            GenerateScriptSectionAgent::class,
            GenerateScriptClosingAgent::class,
            GeneratePracticeArtifactAgent::class,
            ReviewScriptAgent::class,
            ReviewPracticeAgent::class,
            ProposeCourseBibleAgent::class,
        ];

        foreach ($agents as $agent) {
            $instance = app($agent);
            $messages = $instance->messages();

            expect($instance)->toBeInstanceOf(Conversational::class)
                ->and(is_array($messages) ? $messages : iterator_to_array($messages))->toBe([]);
        }
    });
});

describe('knowledge enrichment', function (): void {
    it('appends related passages last so the stable prefix is untouched', function (): void {
        $renderer = app(WritingContextRenderer::class);
        $plain = $renderer->prompt(WritingContextFixture::video22(), 'REQUEST: x');

        $context = WritingContextFixture::video22()
            ->withRelatedContext(['video 5 – Tema: Google Drive y documentos.']);
        $enriched = $renderer->prompt($context, 'REQUEST: x');

        expect($enriched->layers)->toHaveCount(3)
            ->and($enriched->layers[0]->text)->toBe($plain->layers[0]->text)
            ->and($enriched->layers[1]->text)->toBe($plain->layers[1]->text)
            ->and($enriched->layers[2]->text)->toContain('RELATED PASSAGES')
            ->and($enriched->asSingleMessage())->toContain('Google Drive');
    });

    it('keeps two layers when there is no related context', function (): void {
        $prompt = app(WritingContextRenderer::class)->prompt(WritingContextFixture::video22(), 'REQUEST: x');

        expect($prompt->layers)->toHaveCount(2);
    });
});

describe('outline preview stream', function (): void {
    it('redirects guests to login', function (): void {
        $this->get(route('course-scripts.videos.outline.preview', ['uuid' => Str::uuid(), 'videoUuid' => Str::uuid()]))
            ->assertRedirect(route('login'));
    });

    it('forbids users without the generate permission', function (): void {
        $this->actingAs(CourseScriptTestUsers::withoutPermissions())
            ->get(route('course-scripts.videos.outline.preview', ['uuid' => Str::uuid(), 'videoUuid' => Str::uuid()]))
            ->assertForbidden();
    });

    it('rejects unknown providers before touching any provider', function (): void {
        $this->actingAs(CourseScriptTestUsers::author())
            ->get(route('course-scripts.videos.outline.preview', ['uuid' => Str::uuid(), 'videoUuid' => Str::uuid(), 'provider' => 'nope']))
            ->assertStatus(422);
    });

    it('returns 404 for unknown courses', function (): void {
        $this->actingAs(CourseScriptTestUsers::author())
            ->get(route('course-scripts.videos.outline.preview', ['uuid' => Str::uuid(), 'videoUuid' => Str::uuid()]))
            ->assertNotFound();
    });

    it('clears the prompt-cache scope around streaming', function (): void {
        RecordingAiClient::install([
            GenerateScriptOutlineAgent::class => CanonicalScriptFixture::outlinePayload(),
        ]);

        app(LaravelAiScriptWriterAdapter::class)->outline(WritingContextFixture::video22(), 'anthropic');

        expect(app(PromptCacheScope::class)->current())->toBeNull();
    });
});

describe('embeddings cache', function (): void {
    it('is enabled on the redis store', function (): void {
        expect((bool) config('ai.caching.embeddings.cache'))->toBeTrue()
            ->and((string) config('ai.caching.embeddings.store'))->toBeString();
    });
});
