<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Contact Support module
|--------------------------------------------------------------------------
|
| Everything the public contact-form pipeline needs to tune without a deploy:
| the content-heuristic spam scoring thresholds and the "new message" operator
| notification. The route already carries spatie/laravel-honeypot
| (ProtectAgainstSpam) and a per-IP throttle; the values below are the second
| layer — content scoring applied to submissions that got past the bot traps
| (OWASP §15.5).
|
*/

return [

    /*
     * SpamGuard — content heuristics run inside SubmitContactSupportHandler.
     * A submission scoring at or above `threshold` is stored with
     * `is_spam = true`; `spam_reasons` always records which signals fired so an
     * operator can restore a false positive with context.
     */
    'spam' => [
        'threshold' => (int) env('CONTACT_SUPPORT_SPAM_THRESHOLD', 60),

        /*
         * Per-signal weights. Tuned so a normal enquiry scores 0 and an
         * obvious link/keyword flood clears the threshold on its own.
         */
        'weights' => [
            'link_per_url' => (int) env('CONTACT_SUPPORT_SPAM_LINK_PER_URL', 20),
            'link_markup' => (int) env('CONTACT_SUPPORT_SPAM_LINK_MARKUP', 35),
            'keyword_per_hit' => (int) env('CONTACT_SUPPORT_SPAM_KEYWORD_PER_HIT', 25),
            'shouting' => (int) env('CONTACT_SUPPORT_SPAM_SHOUTING', 15),
            'repetition' => (int) env('CONTACT_SUPPORT_SPAM_REPETITION', 10),
            'disposable_email' => (int) env('CONTACT_SUPPORT_SPAM_DISPOSABLE_EMAIL', 25),
            'name_contains_link' => (int) env('CONTACT_SUPPORT_SPAM_NAME_LINK', 30),
            'gibberish_phone' => (int) env('CONTACT_SUPPORT_SPAM_GIBBERISH_PHONE', 10),
        ],

        /*
         * Two or more links in a short enquiry is the single strongest signal
         * of contact-form spam. Links up to and including this count are free;
         * every link beyond it is weighted with `weights.link_per_url`.
         */
        'free_links' => (int) env('CONTACT_SUPPORT_SPAM_FREE_LINKS', 1),

        /*
         * Lower-cased substrings. A hit anywhere in subject or message adds
         * `weights.keyword_per_hit` (capped at two hits so a single unlucky
         * word never dominates the verdict).
         */
        'blocklist' => [
            'viagra', 'cialis', 'casino', 'poker', 'bitcoin', 'crypto',
            'forex', 'binary options', 'payday loan', 'seo services',
            'buy backlinks', 'guest post', 'increase your ranking',
            'search engine ranking', 'make money fast', 'work from home',
            'weight loss', 'as seen on', 'this is not a scam', 'nigerian prince',
            'click here now', 'limited time offer', 'act now', 'wire transfer',
        ],

        /*
         * Throw-away inbox providers. Matched against the email domain and any
         * parent domain (so `foo.mailinator.com` also matches `mailinator.com`).
         */
        'disposable_domains' => [
            'mailinator.com', 'guerrillamail.com', 'guerrillamail.info',
            'sharklasers.com', '10minutemail.com', 'tempmail.com',
            'temp-mail.org', 'throwawaymail.com', 'yopmail.com', 'trashmail.com',
            'getnada.com', 'dispostable.com', 'maildrop.cc', 'fakeinbox.com',
            'mailnesia.com', 'mvrht.net', 'spam4.me', 'mohmal.com',
        ],
    ],

    /*
     * Operator notification — "a new contact request arrived". Delivered as a
     * queued on-demand mail notification (branded `emails.contact-support.received`
     * view) so the submission response is never blocked on SMTP.
     */
    'notifications' => [
        'enabled' => (bool) env('CONTACT_SUPPORT_NOTIFY_ENABLED', true),

        /*
         * Recipient override. Null (the default) routes to the company inbox
         * from App\Models\CompanyData (Shared\Infrastructure\Company\CompanyProfile),
         * falling back to config('mail.from.address') when that row is empty.
         */
        'recipient' => env('CONTACT_SUPPORT_NOTIFY_RECIPIENT'),

        /*
         * Whether a submission SpamGuard flagged as spam still triggers the
         * operator email. Off by default — the point of the filter is to keep
         * the inbox clean; flagged rows are still visible in the admin "Spam"
         * folder.
         */
        'include_spam' => (bool) env('CONTACT_SUPPORT_NOTIFY_INCLUDE_SPAM', false),
    ],
];
