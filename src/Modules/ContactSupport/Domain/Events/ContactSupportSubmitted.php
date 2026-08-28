<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Domain\Events;

use Modules\ContactSupport\Application\Commands\SubmitContactSupportHandler;

/**
 * Raised by {@see SubmitContactSupportHandler}
 * once a public landing-page submission has been persisted, with the SpamGuard
 * verdict already attached to the row.
 *
 * Drives the queued operator "new contact request" email. Carries the public
 * `uuid` only — the listener reloads the row so nothing stale rides the queue.
 */
final readonly class ContactSupportSubmitted
{
    public function __construct(
        public string $uuid,
        public bool $isSpam,
    ) {}
}
