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
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\LeadScout\Application\Commands\ExtractSignalsHandler;
use Modules\LeadScout\Application\Commands\UpdateBudgetsHandler;
use Modules\LeadScout\Application\DTOs\UpdateBudgetsData;
use Modules\LeadScout\Domain\Exceptions\BudgetExceededException;
use Modules\LeadScout\Domain\Ports\AiModelCatalogPort;
use Modules\LeadScout\Infrastructure\Ai\ExtractCompanySignalsAgent;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutAiSettingEloquentModel;
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

/**
 * Point a purpose at a provider/model the way the operator would, through the
 * stored row.
 *
 * Precedence is `stored row > .env > config default`, deliberately: the
 * settings UI is the operator's explicit choice and must survive a seeder or
 * an env edit. Tests that want to exercise the env path have to remove the row
 * instead of only overriding config, or they assert nothing.
 */
function setStoredPurpose(string $purpose, string $provider, string $model, ?string $fallbackProvider = null, ?string $fallbackModel = null): void
{
    ScoutAiSettingEloquentModel::query()
        ->updateOrCreate(
            ['purpose' => $purpose],
            [
                'provider' => $provider,
                'model' => $model,
                'fallback_provider' => $fallbackProvider,
                'fallback_model' => $fallbackModel,
            ],
        );
}

function forgetStoredPurpose(string $purpose): void
{
    ScoutAiSettingEloquentModel::query()
        ->where('purpose', $purpose)
        ->delete();
}

it('lets the operator name any model in env, once no row overrides it', function (): void {
    // The point of the relaxation: a new model (Sonnet 6) ships as one .env
    // line, not a PHP edit to a hardcoded catalog. This only holds while the
    // purpose has no stored row — see the precedence test below for the case
    // where it does.
    config()->set('ai.providers.anthropic.key', 'test-anthropic-key');
    forgetStoredPurpose('drafting');
    config()->set('lead-scout.ai_defaults.drafting', [
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-6',
        'fallback_provider' => null,
        'fallback_model' => null,
    ]);

    $resolved = app(AiModelCatalogPort::class)->resolve('drafting');

    expect($resolved['provider'])->toBe('anthropic')
        ->and($resolved['model'])->toBe('claude-sonnet-6');
});

it('keeps the stored row above env, so a UI choice is never overwritten', function (): void {
    // The operator's explicit selection outranks the env. Otherwise editing
    // .env would silently revert a choice made in the settings dialog.
    config()->set('ai.providers.anthropic.key', 'test-anthropic-key');
    setStoredPurpose('drafting', 'anthropic', 'claude-sonnet-5');
    config()->set('lead-scout.ai_defaults.drafting', [
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-6',
        'fallback_provider' => null,
        'fallback_model' => null,
    ]);

    expect(app(AiModelCatalogPort::class)->resolve('drafting')['model'])->toBe('claude-sonnet-5');
});

it('still refuses to price an uncatalogued model at zero', function (): void {
    // The load-bearing half. Pricing an unknown model at zero would let the
    // `ai` budget be bypassed by naming a model the catalog never heard of:
    // ensure() would approve every call. The default rate is the PAID tier, so
    // an over-estimate trips the budget early — the recoverable direction.
    $catalog = app(AiModelCatalogPort::class);

    $known = $catalog->rates('claude-sonnet-5');
    $unknown = $catalog->rates('claude-sonnet-6');

    expect($unknown)->toBe($known)
        ->and($unknown['input_per_mtok_usd'])->toBeGreaterThan(0.0)
        ->and($unknown['output_per_mtok_usd'])->toBeGreaterThan(0.0)
        ->and((float) config('lead-scout.ai_pricing_default.input_per_mtok_usd'))->toBeGreaterThan(0.0);
});

it('lets the settings dialog round-trip its own uncatalogued value', function (): void {
    config()->set('ai.providers.anthropic.key', 'test-anthropic-key');
    setStoredPurpose('drafting', 'anthropic', 'claude-sonnet-6');

    $catalog = app(AiModelCatalogPort::class);

    // Re-saving the ACTIVE value must not 422, or the dialog looks broken for
    // an operator running a model the catalog does not list.
    expect($catalog->resolve('drafting', 'anthropic', 'claude-sonnet-6')['model'])->toBe('claude-sonnet-6');

    // But a request still cannot inject a model that is not the active one.
    expect(fn () => $catalog->resolve('drafting', 'anthropic', 'modelo-inventado'))
        ->toThrow(ValidationException::class);

    // And it appears in the selector, labelled as uncatalogued, so the select
    // is not blank.
    $active = collect($catalog->options('drafting'))->firstWhere('model', 'claude-sonnet-6');

    expect($active)->not->toBeNull()
        ->and($active['active_uncatalogued'])->toBeTrue()
        ->and($active['est_cost_per_100_usd'])->toBeGreaterThan(0.0);
});

it('still enforces that the provider serves a catalogued model', function (): void {
    config()->set('ai.providers.anthropic.key', 'test-anthropic-key');
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    setStoredPurpose('drafting', 'gemini', 'claude-sonnet-5');

    expect(fn () => app(AiModelCatalogPort::class)->resolve('drafting'))
        ->toThrow(ValidationException::class);
});

it('lists ai options with availability and edits defaults within the catalog', function (): void {
    $admin = aiAdmin();

    // Hermetic: availability must not depend on a real key in .env.
    config()->set('ai.providers.gemini.key', null);
    config()->set('ai.providers.anthropic.key', null);

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

/**
 * Expected spend in micros for the extraction primary, from the catalog rates.
 *
 * Derived rather than hardcoded: this test exists to prove that real token
 * usage is priced, not to pin one model. A provider or model swap is then a
 * config change and the assertion still has teeth.
 */
function expectedExtractionSpend(int $promptTokens, int $completionTokens): int
{
    $resolved = app(AiModelCatalogPort::class)->resolve('extraction');
    $rates = (array) config('lead-scout.ai_catalog.'.$resolved['model'], []);

    return (int) round(
        $promptTokens * (float) ($rates['input_per_mtok_usd'] ?? 0)
        + $completionTokens * (float) ($rates['output_per_mtok_usd'] ?? 0),
    );
}

it('verifies excerpts literally, records provider and prices real usage', function (): void {
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    config()->set('ai.providers.anthropic.key', 'test-anthropic-key');

    $primary = app(AiModelCatalogPort::class)->resolve('extraction');
    $spy = RecordingAiClient::install([ExtractCompanySignalsAgent::class => extractionPayload()]);
    $company = signalCompany();

    $report = app(ExtractSignalsHandler::class)->handle($company->uuid);

    expect($report['ai'])->toBe(1)
        ->and($report['discarded'])->toBe(2)
        ->and($report['provider'])->toBe($primary['provider'])
        ->and($report['model'])->toBe($primary['model']);

    $signal = ScoutSignalEloquentModel::query()
        ->where('company_id', $company->id)->where('signal_key', 'remote')->firstOrFail();

    expect($signal->extraction_method->value)->toBe('ai')
        ->and($signal->ai_provider)->toBe($primary['provider'])
        ->and($signal->ai_model)->toBe($primary['model']);

    // Real token usage priced the budget, at the primary's own rates.
    expect(ScoutBudgetEloquentModel::query()
        ->where('category', 'ai')->value('spent_micros'))->toBe(expectedExtractionSpend(8000, 500));

    $prompt = $spy->calls[0]['prompt'];

    // Team pages never travel (their URL slips through in no header: only
    // page bodies are sent, and the team body is excluded).
    expect($prompt)->not->toContain('Ana Ruiz', 'ana@example.com', '+34 910 111 222', 'Pedro Gil');
});

it('verifies the configured primary is a paid tier and Gemini is the fallback', function (): void {
    // Pins the deployment DECISION, not the model ids. Extraction is one call
    // per scraped page, so the primary must be the provider that can sustain
    // the volume: the Gemini free tier allows roughly one generation request
    // per minute, which is below what this stage needs.
    //
    // Model ids are deliberately NOT asserted. They are operator-configurable
    // through `.env` by design now, and a test that pins `gemini-3.7-flash`
    // would fail on a routine model bump while proving nothing about the
    // behaviour that matters. Provider choice is the stable part.
    $catalog = app(AiModelCatalogPort::class);
    $extraction = $catalog->resolve('extraction');
    $drafting = $catalog->resolve('drafting');

    expect($extraction['provider'])->toBe('anthropic')
        ->and($extraction['fallback_provider'])->toBe('gemini')
        ->and($drafting['provider'])->toBe('anthropic')
        ->and($drafting['fallback_provider'])->toBe('gemini')
        // A model must be named on both sides, or the failover is a no-op.
        ->and($extraction['model'])->toBeString()->not->toBe('')
        ->and($extraction['fallback_model'])->toBeString()->not->toBe('')
        // And the primary must actually be priceable, or the budget guard is
        // silently disabled for it.
        ->and($catalog->rates($extraction['model'])['input_per_mtok_usd'])->toBeGreaterThan(0.0);
});

it('falls back to the secondary model when the primary fails', function (): void {
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    config()->set('ai.providers.anthropic.key', 'test-anthropic-key');

    $fallback = app(AiModelCatalogPort::class)->resolve('extraction');

    $inner = new RecordingAiClient([ExtractCompanySignalsAgent::class => ['signals' => [], 'company_type' => 'other']]);
    $flaky = new FlakyAiClient($inner);
    app()->instance(AIClientInterface::class, $flaky);

    $company = signalCompany();
    $report = app(ExtractSignalsHandler::class)->handle($company->uuid);

    expect($report['provider'])->toBe($fallback['fallback_provider'])
        ->and($report['model'])->toBe($fallback['fallback_model'])
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

    // The primary failed, so the report carries the fallback that succeeded.
    expect($report['provider'])->toBe(app(AiModelCatalogPort::class)->resolve('extraction')['fallback_provider']);

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $event, array $context): bool => $event === 'lead-scout.ai_extraction_failed'
            // The redacted error belongs to the provider that FAILED, which is
            // the configured primary — not the one that ended up serving.
            && $context['provider'] === app(AiModelCatalogPort::class)->resolve('extraction')['provider']
            && str_contains($context['error'], '[email]')
            && ! str_contains($context['error'], 'ana@example.com'),
    );
});

it('rejects commercial excerpts without semantic support (5c)', function (): void {
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    RecordingAiClient::install([ExtractCompanySignalsAgent::class => [
        'signals' => [
            [
                // Literally present in the page, but semantically empty: no
                // freelanc*/partner*/colabor*/subcontrat*/marca blanca/white
                // label/extern* term supports the commercial claim.
                'signal_key' => 'accepts_external',
                'nature' => 'fact',
                'excerpt' => 'Nuestro equipo trabaja en remoto.',
                'confidence' => 90,
                'source_url' => 'https://semantica.example/servicios',
            ],
            [
                'signal_key' => 'accepts_external',
                'nature' => 'fact',
                'excerpt' => 'Hacemos partners de integración.',
                'confidence' => 90,
                'source_url' => 'https://semantica.example/servicios',
            ],
        ],
        'company_type' => 'other',
        'team_size_observed' => null,
    ]]);

    // Controlled page: no rule-level accepts_external cue (no "partner
    // program"/"trabajamos con freelancers"), so the AI verdict decides.
    $company = ScoutCompanyFactory::new()->create(['canonical_domain' => 'semantica.example']);
    ScoutFetchedPageEloquentModel::query()->create([
        'company_id' => $company->id,
        'url' => 'https://semantica.example/servicios',
        'page_type' => 'services',
        'content_markdown' => 'Desarrollamos con Laravel. Somos una agencia de software. Nuestro equipo trabaja en remoto. Hacemos partners de integración.',
        'content_hash' => hash('sha256', 'semantic-5c'),
        'fetched_at' => now(),
    ]);

    $report = app(ExtractSignalsHandler::class)->handle($company->uuid);

    expect($report['ai'])->toBe(1)
        ->and($report['discarded'])->toBe(1)
        ->and(ScoutSignalEloquentModel::query()
            ->where('company_id', $company->id)->where('signal_key', 'accepts_external')->value('evidence_excerpt'))
        ->toContain('partners');
});

it('rejects sitemap and url-list excerpts as commercial proof (5c)', function (): void {
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    RecordingAiClient::install([ExtractCompanySignalsAgent::class => [
        'signals' => [
            [
                'signal_key' => 'accepts_external',
                'nature' => 'fact',
                'excerpt' => '<loc>https://example.com/partners</loc><lastmod>2026-08-01</lastmod> white label',
                'confidence' => 90,
                'source_url' => 'https://example.com/sitemap.xml',
            ],
            [
                'signal_key' => 'freelance_contract',
                'nature' => 'fact',
                'excerpt' => 'Freelance work at https://a.example/1 https://a.example/2 https://a.example/3',
                'confidence' => 90,
                'source_url' => 'https://example.com/servicios',
            ],
        ],
        'company_type' => 'other',
        'team_size_observed' => null,
    ]]);

    // The source text must literally contain the excerpts for the
    // sitemap-rejection (not the literal check) to be what discards them.
    $company = signalCompany();
    ScoutFetchedPageEloquentModel::query()->where('company_id', $company->id)->update([
        'content_markdown' => 'Desarrollamos con Laravel. Trabajamos con freelancers. Nuestro equipo trabaja en remoto. '
            .'<loc>https://example.com/partners</loc><lastmod>2026-08-01</lastmod> white label '
            .'Freelance work at https://a.example/1 https://a.example/2 https://a.example/3',
    ]);

    $report = app(ExtractSignalsHandler::class)->handle($company->uuid);

    expect($report['ai'])->toBe(0)->and($report['discarded'])->toBe(2);
});

it('spends no model call when the rules already discarded the lead', function (): void {
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    $spy = RecordingAiClient::install([ExtractCompanySignalsAgent::class => extractionPayload()]);
    $company = signalCompany();

    // A parked/sole-trader/dead-web verdict cannot be rescued by a model call
    // (2026-09-25: every junk page paid for a full Gemini/Sonnet extraction).
    ScoutSignalEloquentModel::query()->create([
        'company_id' => $company->id,
        'dimension' => 'vitality',
        'signal_key' => 'solo_freelancer',
        'nature' => 'fact',
        'confidence' => 85,
        'evidence_url' => 'https://example.com/',
        'evidence_excerpt' => 'Natural person (sole trader).',
        'captured_at' => now(),
        'extraction_method' => 'rule',
    ]);

    $report = app(ExtractSignalsHandler::class)->handle($company->uuid);

    expect($report['ai'])->toBe(0)
        ->and($report['provider'])->toBeNull()
        ->and($spy->calls)->toBeEmpty();
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
