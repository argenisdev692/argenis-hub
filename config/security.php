<?php

declare(strict_types=1);

return [

    /*
     |--------------------------------------------------------------------------
     | Security Response Headers
     |--------------------------------------------------------------------------
     |
     | Consumed by Shared\Infrastructure\Http\Middleware\SecurityHeaders, the
     | middleware that actually emits the Content-Security-Policy header. It is
     | appended to the GLOBAL middleware stack in bootstrap/app.php, so these
     | values apply to every route, web and API alike.
     |
     */

    'headers' => [

        /*
         | Capabilities the browser must deny to this origin. Empty value = no
         | permission, so every feature gate stays off unless added explicitly.
         */
        'permissions_policy' => env(
            'PERMISSIONS_POLICY',
            'camera=(), geolocation=(), microphone=(), payment=(), usb=(), interest-cohort=()'
        ),

        /*
         | HTTP Strict Transport Security. Only emitted on secure requests, so
         | plain-HTTP local development is never pinned.
         */
        'hsts' => [
            'enabled' => env('HSTS_ENABLED', true),
            'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
            'include_subdomains' => env('HSTS_INCLUDE_SUBDOMAINS', false),
            'preload' => env('HSTS_PRELOAD', false),
        ],

        /*
         | Extra origins appended to the nonce-based CSP for the whole application.
         | Each entry is in addition to `'self'`; the base policy already locks
         | default-src, base-uri, object-src, frame-ancestors and form-action.
         |
         | The R2 bucket origin is deliberately absent: it is derived at request
         | time from `filesystems.disks.r2` by DirectUploadStoragePreset, so it
         | follows the endpoint instead of being duplicated (and going stale) here.
         */
        'csp' => [

            // Places Autocomplete (New) — loader script plus its XHRs.
            'script_src' => [
                'https://maps.googleapis.com',
            ],
            'connect_src' => [
                'https://maps.googleapis.com',
                'https://places.googleapis.com',
            ],

            // Inline data URIs (logo, generated previews) and Google imagery.
            'img_src' => [
                'data:',
                'blob:',
                'https://maps.gstatic.com',
                'https://*.googleusercontent.com',
            ],

            // Webfonts are self-hosted from /build/assets, so `'self'` is enough.
            'font_src' => [
                'data:',
            ],
        ],
    ],

];
