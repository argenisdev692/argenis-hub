<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\Post\Domain\Enums\PostAiGenerationStatus;
use Modules\Post\Infrastructure\Ai\EvaluatePostContentAgent;
use Modules\Post\Infrastructure\Ai\GeneratePostContentAgent;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerOpenException;

/*
| What the quality loop does when the provider is DOWN rather than flaky.
|
| The distinction is the whole point: a single provider error is worth another
| attempt, but an open circuit breaker rejects every call locally without
| leaving the process. Retrying into it four more times buys nothing and buries
| the real cause under a generic "failed on every iteration".
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    config()->set('ai.default_for_evaluation', 'anthropic');
    Cache::flush();
});

function breakerAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

/**
 * Binds an AI client that serves `$successfulCalls` structured responses from
 * the agent fakes and then behaves as a tripped breaker for every call after
 * that. Returns a counter so a test can assert HOW MANY calls were attempted.
 */
function breakerAfter(int $successfulCalls): object
{
    Storage::fake((string) config('filesystems.cloud', 'r2'));

    $spy = new class
    {
        public int $calls = 0;
    };

    $inner = app(AIClientInterface::class);

    app()->instance(AIClientInterface::class, new class($inner, $spy, $successfulCalls) implements AIClientInterface
    {
        public function __construct(
            private AIClientInterface $inner,
            private object $spy,
            private int $successfulCalls,
        ) {}

        public function generateStructured(string $agentClass, string $prompt, ?string $provider = null): StructuredAgentResponse
        {
            $this->spy->calls++;

            if ($this->spy->calls > $this->successfulCalls) {
                throw CircuitBreakerOpenException::for('ai:structured:'.($provider ?? 'default'));
            }

            return $this->inner->generateStructured($agentClass, $prompt, $provider);
        }

        /**
         * @return array{base64: string, mime: string}
         */
        public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
        {
            return ['base64' => base64_encode('fake-png-bytes'), 'mime' => 'image/png'];
        }
    });

    return $spy;
}

it('stops after one attempt instead of retrying into an open breaker', function (): void {
    GeneratePostContentAgent::fake([postCostDraft()]);
    EvaluatePostContentAgent::fake([postCostVerdict()]);

    $spy = breakerAfter(0);

    $generation = runPostGeneration(breakerAdmin(), [
        'topic' => 'Provider down',
        'provider' => 'openai',
        'image_mode' => 'none',
    ]);

    expect($generation['status'])->toBe(PostAiGenerationStatus::Failed->value)
        ->and($generation['is_terminal'])->toBeTrue()
        ->and($generation['result'])->toBeNull()
        // One attempt, not MAX_ITERATIONS. This is the assertion the whole fix
        // exists for.
        ->and($spy->calls)->toBe(1);
});

it('reports an open breaker as a provider outage, not as a quality failure', function (): void {
    GeneratePostContentAgent::fake([postCostDraft()]);
    EvaluatePostContentAgent::fake([postCostVerdict()]);

    breakerAfter(0);

    $generation = runPostGeneration(breakerAdmin(), [
        'topic' => 'Outage message',
        'provider' => 'openai',
        'image_mode' => 'none',
    ]);

    expect($generation['error_message'])
        ->toContain('temporarily unavailable')
        ->and($generation['error_message'])->toContain('circuit breaker is open')
        ->and($generation['error_message'])->not->toContain('failed on every iteration');
});

it('keeps a usable earlier draft when the breaker opens mid-run', function (): void {
    // Iteration 1 writes and is judged below threshold, so the loop wants a
    // second attempt. The breaker opens before it can start.
    GeneratePostContentAgent::fake([
        postCostDraft('Survivor'),
        postCostDraft('Never written'),
    ]);
    EvaluatePostContentAgent::fake([
        postCostVerdict(['virality_score' => 40]),
        postCostVerdict(),
    ]);

    // 2 successful calls = iteration 1's write + judge. Iteration 2's write trips.
    $spy = breakerAfter(2);

    $generation = runPostGeneration(breakerAdmin(), [
        'topic' => 'Partial run',
        'provider' => 'openai',
        'image_mode' => 'none',
    ]);

    // Finished work is not thrown away because the NEXT attempt could not start.
    expect($generation['status'])->toBe(PostAiGenerationStatus::Completed->value)
        ->and($generation['result']['title'])->toBe('Survivor')
        ->and($generation['result']['quality_warning'])->toBeTrue()
        ->and($generation['result']['virality_score'])->toBe(40)
        // The attempt that never reached the provider is not counted as one.
        ->and($generation['result']['iterations_required'])->toBe(1)
        // …and the warning names the real reason rather than blaming quality.
        ->and($generation['result']['quality_warning_message'])
        ->toContain('became unavailable mid-run')
        ->and($spy->calls)->toBe(3);
});

it('treats a plain provider error as retryable, unlike an open breaker', function (): void {
    // Same shape as the test above, but the failure is an ordinary exception:
    // the loop must keep going and use its remaining attempts.
    GeneratePostContentAgent::fake(array_fill(0, 5, postCostDraft('Retried')));
    EvaluatePostContentAgent::fake(array_fill(0, 5, postCostVerdict(['virality_score' => 40])));

    Storage::fake((string) config('filesystems.cloud', 'r2'));

    $inner = app(AIClientInterface::class);
    $spy = new class
    {
        public int $calls = 0;
    };

    app()->instance(AIClientInterface::class, new class($inner, $spy) implements AIClientInterface
    {
        public function __construct(private AIClientInterface $inner, private object $spy) {}

        public function generateStructured(string $agentClass, string $prompt, ?string $provider = null): StructuredAgentResponse
        {
            $this->spy->calls++;

            // Trip only the very first write, then behave normally.
            if ($this->spy->calls === 1) {
                throw new RuntimeException('transient 429');
            }

            return $this->inner->generateStructured($agentClass, $prompt, $provider);
        }

        /**
         * @return array{base64: string, mime: string}
         */
        public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
        {
            return ['base64' => base64_encode('fake-png-bytes'), 'mime' => 'image/png'];
        }
    });

    $generation = runPostGeneration(breakerAdmin(), [
        'topic' => 'Transient error',
        'provider' => 'openai',
        'image_mode' => 'none',
    ]);

    expect($generation['status'])->toBe(PostAiGenerationStatus::Completed->value)
        ->and($generation['result']['title'])->toBe('Retried')
        // It kept going past the hiccup rather than stopping the way an open
        // breaker does.
        ->and($spy->calls)->toBeGreaterThan(1);
});
