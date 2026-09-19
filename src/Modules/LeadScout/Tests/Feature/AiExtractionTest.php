<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\ScoutCompanyFactory;
use Database\Seeders\LeadScoutAiSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\LeadScout\Application\Commands\ExtractSignalsHandler;
use Modules\LeadScout\Application\Commands\UpdateBudgetsHandler;
use Modules\LeadScout\Application\DTOs\UpdateBudgetsData;
use Modules\LeadScout\Domain\Exceptions\BudgetExceededException;
use Modules\LeadScout\Infrastructure\Ai\ExtractCompanySignalsAgent;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutBudgetEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutFetchedPageEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSignalEloquentModel;
use Modules\LeadScout\Tests\Support\FlakyAiClient;
use Modules\LeadScout\Tests\Support\RecordingAiClient;
use Shared\Infrastructure\AI\AIClientInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(LeadScoutAiSettingsSeeder::class);
    Queue::fake();
});

function aiAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function signalCompany(): ScoutCompanyEloquentModel
{
    $company = ScoutCompanyFactory::new()->spanishAgency()->create();

    ScoutFetchedPageEloquentModel::query()->create([
        'company_id' => $company->id,
        'url' => 'https://'.$company->canonical_domain.'/servicios',
        'page_type' => 'services',
        'content_markdown' => 'Desarrollamos con Laravel. Trabajamos con freelancers. Nuestro equipo trabaja en remoto.',
        'content_hash' => hash('sha256', 'x'),
        'fetched_at' => now(),
    ]);

    ScoutFetchedPageEloquentModel::query()->create([
        'company_id' => $company->id,
        'url' => 'https://'.$company->canonical_domain.'/equipo',
        'page_type' => 'team',
        'content_markdown' => "**Ana Ruiz** — CEO\nContacto: ana@example.com, +34 910 111 222.\nTestimonio de Cliente: \"genial\" — Pedro Gil, CEO de X.",
        'content_hash' => hash('sha256', 'y'),
        'fetched_at' => now(),
    ]);

    return $company;
}

function extractionPayload(): array
{
    return [
        'signals' => [
            [
                'signal_key' => 'remote',
                'nature' => 'fact',
                'excerpt' => 'Nuestro equipo trabaja en remoto.',
                'confidence' => 80,
                'source_url' => 'https://example.com/servicios',
            ],
            [
                'signal_key' => 'magic_unicorns',
                'nature' => 'fact',
                'excerpt' => 'Trabajamos con freelancers.',
                'confidence' => 99,
                'source_url' => 'https://example.com/servicios',
            ],
            [
                'signal_key' => 'maintenance_sla',
                'nature' => 'inference',
                'excerpt' => 'Invented sentence never published.',
                'confidence' => 90,
                'source_url' => 'https://example.com/servicios',
            ],
        ],
        'company_type' => 'software_agency',
        'team_size_observed' => 12,
    ];
}

it('lists ai options with availability and edits defaults within the catalog', function (): void {
    $admin = aiAdmin();

    $shown = $this->actingAs($admin)->getJson('/data/admin/lead-scout/ai-settings')->assertOk()->json('data');

    expect($shown['purposes']['extraction']['options'][0])->toHaveKeys(['provider', 'model', 'available', 'est_cost_per_100_usd'])
        ->and($shown['purposes']['extraction']['options'][0]['available'])->toBeFalse();

    $this->actingAs($admin)
        ->putJson('/data/admin/lead-scout/ai-settings', [
            'purpose' => 'extraction', 'provider' => 'nope', 'model' => 'nope-1',
        ])
        ->assertUnprocessable();

    config()->set('ai.providers.gemini.key', 'test-gemini-key');

    $this->actingAs($admin)
        ->putJson('/data/admin/lead-scout/ai-settings', [
            'purpose' => 'extraction', 'provider' => 'gemini', 'model' => 'gemini-3.7-flash',
        ])
        ->assertOk()
        ->assertJsonPath('data.purposes.extraction.model', 'gemini-3.7-flash');
});

it('verifies excerpts literally, records provider and prices real usage', function (): void {
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    $spy = RecordingAiClient::install([ExtractCompanySignalsAgent::class => extractionPayload()]);
    $company = signalCompany();

    $report = app(ExtractSignalsHandler::class)->handle($company->uuid);

    expect($report['ai'])->toBe(1)
        ->and($report['discarded'])->toBe(2)
        ->and($report['provider'])->toBe('gemini')
        ->and($report['model'])->toBe('gemini-3.7-flash');

    $signal = ScoutSignalEloquentModel::query()
        ->where('company_id', $company->id)->where('signal_key', 'remote')->firstOrFail();

    expect($signal->extraction_method->value)->toBe('ai')
        ->and($signal->ai_provider)->toBe('gemini')
        ->and($signal->ai_model)->toBe('gemini-3.7-flash');

    // Real token usage priced the budget (8000 in + 500 out on Gemini Flash).
    expect(ScoutBudgetEloquentModel::query()
        ->where('category', 'ai')->value('spent_micros'))->toBe(7875);

    $prompt = $spy->calls[0]['prompt'];

    // Team pages never travel (their URL slips through in no header: only
    // page bodies are sent, and the team body is excluded).
    expect($prompt)->not->toContain('Ana Ruiz', 'ana@example.com', '+34 910 111 222', 'Pedro Gil');
});

it('falls back to the secondary model when the primary fails', function (): void {
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    config()->set('ai.providers.anthropic.key', 'test-anthropic-key');

    $inner = new RecordingAiClient([ExtractCompanySignalsAgent::class => ['signals' => [], 'company_type' => 'other']]);
    $flaky = new FlakyAiClient($inner);
    app()->instance(AIClientInterface::class, $flaky);

    $company = signalCompany();
    $report = app(ExtractSignalsHandler::class)->handle($company->uuid);

    expect($report['provider'])->toBe('anthropic')
        ->and($report['model'])->toBe('claude-sonnet-5')
        ->and($flaky->calls)->toBe(2)
        ->and($inner->calls)->toHaveCount(1);
});

it('redacts personal data echoed by a failing provider before logging the fallback', function (): void {
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    config()->set('ai.providers.anthropic.key', 'test-anthropic-key');
    Log::spy();

    $inner = new RecordingAiClient([ExtractCompanySignalsAgent::class => ['signals' => [], 'company_type' => 'other']]);
    app()->instance(AIClientInterface::class, new class($inner) implements AIClientInterface
    {
        private bool $failed = false;

        public function __construct(private readonly RecordingAiClient $inner) {}

        public function generateStructured(string $agentClass, string $prompt, ?string $provider = null, ?string $model = null, ?int $timeoutSeconds = null): StructuredAgentResponse
        {
            if (! $this->failed) {
                $this->failed = true;

                throw new RuntimeException('Invalid request near "contact ana@example.com".');
            }

            return $this->inner->generateStructured($agentClass, $prompt, $provider, $model, $timeoutSeconds);
        }

        public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
        {
            return $this->inner->generateImage($prompt, $provider, $size, $quality);
        }
    });

    $report = app(ExtractSignalsHandler::class)->handle(signalCompany()->uuid);

    expect($report['provider'])->toBe('anthropic');

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $event, array $context): bool => $event === 'lead-scout.ai_extraction_failed'
            && $context['provider'] === 'gemini'
            && str_contains($context['error'], '[email]')
            && ! str_contains($context['error'], 'ana@example.com'),
    );
});

it('drops non-web evidence urls proposed by the model', function (): void {
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    $payload = extractionPayload();
    $payload['signals'][0]['source_url'] = 'javascript:alert(document.cookie)';
    RecordingAiClient::install([ExtractCompanySignalsAgent::class => $payload]);
    $company = signalCompany();

    app(ExtractSignalsHandler::class)->handle($company->uuid);

    $signal = ScoutSignalEloquentModel::query()
        ->where('company_id', $company->id)->where('signal_key', 'remote')->firstOrFail();

    expect($signal->evidence_url)->toBeNull();
});

it('skips the model when rules already cover every dimension', function (): void {
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    $spy = RecordingAiClient::install([ExtractCompanySignalsAgent::class => extractionPayload()]);
    $company = signalCompany();

    foreach (['commercial', 'recurrent', 'communication'] as $dimension) {
        ScoutSignalEloquentModel::query()->create([
            'company_id' => $company->id,
            'dimension' => $dimension,
            'signal_key' => $dimension.'_seed',
            'nature' => 'fact',
            'confidence' => 80,
            'captured_at' => now(),
            'extraction_method' => 'rule',
        ]);
    }

    $company->update(['company_type' => 'software_agency']);

    $report = app(ExtractSignalsHandler::class)->handle($company->uuid);

    expect($report['ai'])->toBe(0)->and($spy->calls)->toBeEmpty();
});

it('makes no model call when the ai budget is exhausted', function (): void {
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    $spy = RecordingAiClient::install([ExtractCompanySignalsAgent::class => extractionPayload()]);
    $company = signalCompany();

    Http::fake();

    app(UpdateBudgetsHandler::class)->handle(
        UpdateBudgetsData::from(['budgets' => [['category' => 'ai', 'limit_eur' => 0]]]),
    );

    try {
        app(ExtractSignalsHandler::class)->handle($company->uuid);
        $this->fail('Expected BudgetExceededException.');
    } catch (BudgetExceededException) {
        expect($spy->calls)->toBeEmpty();
    }

    Http::assertNothingSent();
});
