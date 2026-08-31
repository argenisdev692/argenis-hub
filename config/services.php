<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Maps JavaScript API — Places (New). The browser key is public by design
     * (it ships in the page), so it MUST be locked down in Google Cloud with
     * an HTTP-referrer restriction and limited to the Places API. Never put a
     * server key here.
     */
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    /*
     * Tavily — real-time web research that grounds AI content generation in
     * current trends instead of stale model knowledge (Post + SocialMedia
     * topic ideation and every quality-loop iteration).
     *
     * These keys are REQUIRED, not optional decoration:
     * `TavilyResearchAdapter::search()` early-returns an empty result set when
     * `api_key` resolves to an empty string, and it does so SILENTLY — every
     * generation then runs on the model's own knowledge with no error, no log
     * line and no visible difference except worse, undated content. The block
     * was missing entirely until 2026-08-31.
     */
    'tavily' => [
        'api_key' => env('TAVILY_API_KEY'),
        'url' => env('TAVILY_SEARCH_URL', 'https://api.tavily.com/search'),
        'search_depth' => env('TAVILY_SEARCH_DEPTH', 'advanced'),
        'max_results' => env('TAVILY_MAX_RESULTS', 5),
    ],

    /*
     * ElevenLabs — text-to-speech for the TikTok / Instagram Reels voiceover
     * track. `ElevenLabsSpeechAdapter` returns null when either the key or the
     * voice id is empty, so a missing block degrades to "CapCut timeline with
     * no audio" rather than an error.
     */
    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'voice_id' => env('ELEVENLABS_VOICE_ID'),
        'model_id' => env('ELEVENLABS_MODEL_ID', 'eleven_multilingual_v2'),
    ],

    /*
     * Shared secret for server-side CRM clients (the Astro landing).
     * `EnsureCrmApiToken` is fail-closed: an empty token rejects every request
     * with a 401, which is the intended behaviour for a misconfigured
     * production environment.
     */
    'crm' => [
        'api_token' => env('CRM_API_TOKEN'),
    ],

];
