<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Outbound Provider Adapter
    |--------------------------------------------------------------------------
    |
    | Selects which Shared\Infrastructure\Mail\MailInterface implementation the
    | container binds: "brevo" => BrevoMailAdapter (SMTP relay, the default),
    | "resend" => ResendMailAdapter (Resend HTTPS API). This is NOT the Laravel
    | mailer name — "default" above still wins in tests (array) and local (log),
    | which is what keeps the suite off both providers.
    |
    | Supported: "brevo", "resend"
    |
    */

    'adapter' => env('MAIL_ADAPTER', 'brevo'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        /*
         * Brevo transactional relay — the production outbound transport.
         * Resolved through Shared\Infrastructure\Mail\BrevoMailAdapter and the
         * UsesBrevoMailer trait, never referenced by name from a caller.
         * Credentials are the Brevo SMTP key pair, NOT the account password.
         */
        'brevo' => [
            'transport' => 'smtp',
            'scheme' => env('BREVO_MAIL_SCHEME', 'smtp'),
            'host' => env('BREVO_MAIL_HOST', 'smtp-relay.brevo.com'),
            'port' => (int) env('BREVO_MAIL_PORT', 587),
            'username' => env('BREVO_MAIL_USERNAME'),
            'password' => env('BREVO_MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        /*
         * Resend transactional API — the alternative production transport
         * (resend/resend-laravel v1.4). Resolved through
         * Shared\Infrastructure\Mail\ResendMailAdapter and the UsesResendMailer
         * trait, never referenced by name from a caller. This is an HTTPS API
         * transport, so there is no host/port/credential pair here: the key is
         * `services.resend.key` (RESEND_API_KEY).
         *
         * A per-mailer "from" overrides the global one for this transport only
         * (Illuminate\Mail\MailManager::setGlobalAddress), which lets Resend send
         * from its own verified domain while Brevo keeps MAIL_FROM_ADDRESS.
         */
        'resend' => [
            'transport' => 'resend',
            'from' => [
                'address' => env('RESEND_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
            ],
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

];
