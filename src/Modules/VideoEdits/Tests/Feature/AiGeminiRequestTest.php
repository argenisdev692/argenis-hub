<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\VideoEdits\Infrastructure\Ai\AnalyzeVideoEditAgent;

/**
 * The wire format of the Gemini analysis request, not the option array.
 *
 * `laravel/ai` merges an agent's provider options into `generationConfig`
 * verbatim, so an option name that is not a real `GenerationConfig` field is
 * sent as-is and the whole analysis is rejected with a 400. That is how a flat
 * `thinking_level` came to fail production while the unit-level assertion on the
 * option array kept passing.
 */
it('sends the thinking level inside generationConfig.thinkingConfig', function (): void {
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
    config()->set('ai.providers.gemini.url', 'https://generativelanguage.googleapis.com/v1beta/');
    config()->set('video-edit.ai.thinking_level', 'high');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'role' => 'model',
                    'parts' => [[
                        'text' => '{"cuts":[],"recommendations":[],"conclusion":"Sin cambios."}',
                    ]],
                ],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 5,
                'totalTokenCount' => 15,
            ],
        ]),
    ]);

    app(AnalyzeVideoEditAgent::class)->prompt(
        'Analiza esta transcripcion.',
        provider: 'gemini',
        model: 'gemini-3.8-flash',
        timeout: 30,
    );

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'gemini-3.8-flash:generateContent')) {
            return false;
        }

        $generationConfig = json_decode($request->body(), true)['generationConfig'] ?? [];

        // `thinkingLevel` is a field of `ThinkingConfig`, never of
        // `GenerationConfig`; the flat form is the exact payload the API
        // rejected with `Unknown name "thinking_level"`.
        return ($generationConfig['thinkingConfig']['thinkingLevel'] ?? null) === 'high'
            && ! array_key_exists('thinking_level', $generationConfig);
    });
});
