<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => env('AI_PROVIDER', 'openai'),
    'default_for_images' => env('AI_IMAGE_PROVIDER', 'gemini'),

    /*
     * Quality-gate judge. Content generation lets the caller pick the writing
     * provider per request; the SCORING model is deliberately NOT the same
     * one. A model grading its own draft inflates every score it reports —
     * it has no independent view of the text — so the loop would exit on
     * self-congratulation rather than on quality. Point this at a provider
     * other than the one you generate with; if the two ever resolve to the
     * same provider the gate still runs, but it stops being independent.
     */
    'default_for_evaluation' => env('AI_EVALUATOR_PROVIDER', 'anthropic'),

    'default_for_audio' => 'openai',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => 'openai',
    'default_for_reranking' => 'cohere',

    /*
     * Writer failover order. When the requested writing provider fails with a
     * retryable error, module adapters retry the same prompt on each provider
     * that follows it in this list. The evaluation judge is NOT failed over —
     * it must stay on a different provider than the writer to remain
     * independent. Comma-separated, first match wins.
     */
    'failover_order' => env('AI_FAILOVER_ORDER', 'openai,anthropic'),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => (bool) env('AI_EMBEDDINGS_CACHE', true),
            'store' => env('CACHE_STORE', 'redis'),
            'individually' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider prompt caching (Shared\Infrastructure\AI\PromptCache)
    |--------------------------------------------------------------------------
    |
    | laravel/ai has no prompt-caching API of its own: it reports cache usage
    | (`cache_read_input_tokens`, `cache_creation_input_tokens`) and forwards
    | per-provider request fields through `HasProviderOptions`. The shared
    | PromptCache layer uses that hook so any module can cache the stable
    | prefix of repeated calls:
    |
    | - Anthropic: explicit `cache_control` breakpoints on system blocks.
    | - OpenAI: automatic prefix caching plus a stable `prompt_cache_key`.
    | - Gemini: implicit prefix caching (identical prefix, nothing to send).
    |
    | `long_ttl` applies to layers marked long-lived (Anthropic '5m' | '1h').
    |
    */

    'prompt_cache' => [
        'enabled' => (bool) env('AI_PROMPT_CACHE', true),
        'long_ttl' => env('AI_PROMPT_CACHE_LONG_TTL', '1h'),
        'log_usage' => (bool) env('AI_PROMPT_CACHE_LOG_USAGE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2025-04-01-preview'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
            'image_deployment' => env('AZURE_OPENAI_IMAGE_DEPLOYMENT', 'gpt-image-1'),
            'store' => env('AZURE_OPENAI_STORE', true),
        ],

        'bedrock' => [
            'driver' => 'bedrock',
            'region' => env('AWS_BEDROCK_REGION', 'us-east-1'),
            'key' => env('AWS_BEARER_TOKEN_BEDROCK'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'session_token' => env('AWS_SESSION_TOKEN'),
            'use_default_credential_provider' => env('AWS_USE_DEFAULT_CREDENTIALS', true),
            'assume_role' => [
                'arn' => env('AWS_BEDROCK_ASSUME_ROLE_ARN'),
                'session_name' => env('AWS_BEDROCK_ASSUME_ROLE_SESSION_NAME'),
                'duration_seconds' => env('AWS_BEDROCK_ASSUME_ROLE_DURATION_SECONDS'),
                'external_id' => env('AWS_BEDROCK_ASSUME_ROLE_EXTERNAL_ID'),
            ],
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'store' => env('OPENAI_STORE', true),
        ],

        'openai-compatible' => [
            'driver' => 'openai-compatible',
            'url' => env('OPENAI_COMPATIBLE_URL'),
            'key' => env('OPENAI_COMPATIBLE_API_KEY'),
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-purpose LLM settings (CvJobStudio T-150, CHG-20)
    |--------------------------------------------------------------------------
    |
    | Each AI purpose owns its provider, fallbacks (via `failover_order`
    | above), model per provider, timeout, input/output caps, prompt-cache
    | flag and prompt version. Cache is ON only where reuse is measured to
    | pay for itself (SC-18, T-154): extraction, narrative, tailor and
    | translate share long-lived layers; rewrite, judge and structure-parse
    | run unique inputs and stay uncached.
    |
    */

    'purposes' => [
        'requirement_extraction' => [
            'provider' => 'openai', 'models' => [], 'timeout' => 60,
            'max_input_chars' => 20000, 'max_output_tokens' => 4000,
            'prompt_cache' => true, 'prompt_version' => 'v2',
        ],
        'match_narrative' => [
            'provider' => 'openai', 'models' => [], 'timeout' => 30,
            'max_input_chars' => 8000, 'max_output_tokens' => 500,
            'prompt_cache' => true, 'prompt_version' => 'v2',
        ],
        'tailor' => [
            'provider' => 'openai', 'models' => [], 'timeout' => 90,
            'max_input_chars' => 20000, 'max_output_tokens' => 6000,
            'prompt_cache' => true, 'prompt_version' => 'v2',
        ],
        'translate' => [
            'provider' => 'openai', 'models' => [], 'timeout' => 90,
            'max_input_chars' => 20000, 'max_output_tokens' => 6000,
            'prompt_cache' => true, 'prompt_version' => 'v2',
        ],
        'cv_rewrite' => [
            'provider' => 'openai', 'models' => [], 'timeout' => 120,
            'max_input_chars' => 20000, 'max_output_tokens' => 6000,
            'prompt_cache' => false, 'prompt_version' => 'v2',
        ],
        'cv_judge' => [
            'provider' => 'anthropic', 'models' => [], 'timeout' => 90,
            'max_input_chars' => 20000, 'max_output_tokens' => 4000,
            'prompt_cache' => false, 'prompt_version' => 'v2',
        ],
        'cv_structure_parse' => [
            'provider' => 'openai', 'models' => [], 'timeout' => 90,
            'max_input_chars' => 20000, 'max_output_tokens' => 6000,
            'prompt_cache' => false, 'prompt_version' => 'v2',
        ],
    ],

];
