<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Listeners;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Application\Commands\RecordPasswordChangeHandler;
use Modules\Auth\Application\Commands\RevokeOtherAuthSessionsHandler;
use Modules\Auth\Domain\Events\UserPasswordChanged;
use Modules\Auth\Infrastructure\Notifications\PasswordChangedNotification;

/**
 * Everything that must follow a credential change, defined once (FR-12, FR-13):
 * append to the reuse history, sign every other session out, and tell the owner
 * by email.
 *
 * Not queued: signing other sessions out is a security action that must have
 * taken effect by the time the response is returned.
 *
 * The two writes share one transaction. Half-applying them is the dangerous
 * outcome — a recorded history entry with the old sessions still live would
 * mean the user believes the change locked attackers out when it did not.
 * The notification is deliberately sent AFTER the commit: an email announcing
 * a change that then rolled back is worse than a late email.
 */
final readonly class HandlePasswordChangedListener
{
    public function __construct(
        private RecordPasswordChangeHandler $recordChange,
        private RevokeOtherAuthSessionsHandler $revokeOtherSessions,
        private Request $request,
    ) {}

    public function handle(UserPasswordChanged $event): void
    {
        DB::transaction(function () use ($event): void {
            $this->recordChange->handle($event->userUuid, $event->hashedPassword);

            if ($event->signOutOtherSessions && $this->request->hasSession()) {
                (void) $this->revokeOtherSessions->handle($event->userUuid, $this->request->session()->getId());
            }
        });

        User::query()
            ->where('uuid', $event->userUuid)
            ->first()
            ?->notify(new PasswordChangedNotification(
                ipAddress: $this->request->ip(),
                changedAt: now()->toDayDateTimeString(),
            ));
    }
}
